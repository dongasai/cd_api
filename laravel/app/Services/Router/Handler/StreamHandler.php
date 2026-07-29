<?php

namespace App\Services\Router\Handler;

use App\Models\RequestLog;
use App\Services\ChannelAffinity\ChannelAffinityService;
use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\ProviderManager;
use App\Services\Router\Logger\AuditLogger;
use App\Services\Router\Logger\ResponseLogger;
use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\Enums\FinishReason;
use Generator;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Log;

/**
 * 流式请求处理器
 */
class StreamHandler
{
    protected ProtocolConverter $protocolConverter;

    protected ProviderManager $providerManager;

    protected AuditLogger $auditLogger;

    protected ResponseLogger $responseLogger;

    protected $affinityService = null;  // 渠道亲和性服务

    protected $selectedChannel = null;  // 当前选中的渠道

    public function __construct(
        ProtocolConverter $protocolConverter,
        ProviderManager $providerManager,
        AuditLogger $auditLogger,
        ResponseLogger $responseLogger,
        $affinityService = null
    ) {
        $this->protocolConverter = $protocolConverter;
        $this->providerManager = $providerManager;
        $this->auditLogger = $auditLogger;
        $this->responseLogger = $responseLogger;
        $this->affinityService = $affinityService ?? app(ChannelAffinityService::class);
    }

    /**
     * 处理流式请求
     */
    public function handle(
        HttpRequest $httpRequest,
        ProtocolRequest $protocolRequest,
        $provider,
        string $sourceProtocol,
        string $targetProtocol,
        RequestLog $requestLog,
        float $startTime,  // 保持浮点数精度，用于计算首字延迟
        $auditLog = null,  // 接收已创建的审计日志
        $selectedChannel = null,  // 接收选中的渠道
        $protocolContext = null,  // 协议上下文（状态管理）
        $channelRequestLog = null  // 渠道请求日志实例
    ): Generator {
        $this->selectedChannel = $selectedChannel;  // 保存渠道引用，用于记录亲和性

        // 获取模型名称
        $modelName = $protocolRequest->getModel();

        // 更新审计日志渠道信息（在流开始前确认渠道）
        if ($auditLog !== null && $selectedChannel !== null) {
            $this->auditLogger->update($auditLog, [
                'channel_id' => $selectedChannel->id,
                'channel_name' => $selectedChannel->name,
            ]);
        }

        try {
            $stream = $provider->sendStream($protocolRequest);
        } catch (\Exception $e) {
            // 发送请求失败，立即记录错误
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            if ($auditLog !== null) {
                $this->auditLogger->update($auditLog, [
                    'status_code' => $statusCode,
                    'latency_ms' => $latencyMs,
                    'error_type' => get_class($e),
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }

        // 使用传入的审计日志（如果已创建），否则创建新的
        if ($auditLog === null) {
            $auditLog = $this->auditLogger->createInitial($httpRequest, $modelName, true, $sourceProtocol);
        }

        $firstTokenMs = null;
        $streamChunks = [];
        $collectedUsage = null;
        $collectedFinishReason = null;
        $auditLogUpdated = false;  // 标记审计日志是否已更新（防止重复更新）
        // protocolContext 已通过参数传递，无需从请求提取

        // 检查是否需要过滤 thinking 内容
        $shouldFilterThinking = $selectedChannel !== null && $selectedChannel->shouldFilterThinking();

        try {
            foreach ($stream as $chunk) {
                if ($chunk instanceof StreamChunk) {
                    // 如果开启了 thinking 过滤，跳过 reasoning_delta
                    if ($shouldFilterThinking && $chunk->reasoningDelta !== null) {
                        // 跳过推理内容块
                        continue;
                    }

                    // 记录首字延迟（包括文本内容、推理内容或工具调用）
                    if ($firstTokenMs === null &&
                        ($chunk->delta !== '' ||
                         $chunk->contentDelta !== null ||
                         $chunk->reasoningDelta !== null ||
                         $chunk->toolCalls !== null)) {
                        $firstTokenMs = (int) ((microtime(true) - $startTime) * 1000);
                    }

                    // 收集流式块（直接存储对象）
                    $streamChunks[] = $chunk;

                    // 收集 usage（来自最后一个有 usage 的 chunk）
                    if ($chunk->usage !== null) {
                        $collectedUsage = $chunk->usage;
                    }

                    // 收集 finishReason
                    if ($chunk->finishReason !== null) {
                        $collectedFinishReason = $chunk->finishReason;
                        Log::debug('StreamHandler: 收到 finishReason', [
                            'finish_reason' => $chunk->finishReason->value,
                            'chunk_content_delta' => $chunk->contentDelta,
                            'chunk_reasoning_delta' => $chunk->reasoningDelta,
                            'has_usage' => $chunk->usage !== null,
                        ]);
                    }

                    // 转换并输出
                    $converted = $this->protocolConverter->convertStreamChunk($chunk, $sourceProtocol);
                    if ($converted === '' || $converted === null) {
                        Log::debug('StreamHandler: convertStreamChunk 返回空', [
                            'finish_reason' => $chunk->finishReason?->value,
                            'content_delta' => $chunk->contentDelta,
                            'reasoning_delta' => $chunk->reasoningDelta,
                            'has_usage' => $chunk->usage !== null,
                        ]);
                    } elseif ($chunk->finishReason !== null) {
                        Log::debug('StreamHandler: finishReason chunk 转换结果', [
                            'finish_reason' => $chunk->finishReason->value,
                            'converted_length' => strlen($converted),
                            'converted_preview' => substr($converted, 0, 200),
                        ]);

                        // 关键修复：在 yield finishReason 之前更新审计日志
                        // 因为客户端收到 finishReason 后可能立即断开连接，导致 Generator 后续代码无法执行
                        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                        // 提取实际模型名
                        $actualModel = null;
                        foreach ($streamChunks as $c) {
                            if (! empty($c->model)) {
                                $actualModel = $c->model;
                                break;
                            }
                        }

                        // 提前更新审计日志（包含 token 使用信息）
                        $this->updateAuditLogWithUsage($auditLog, $latencyMs, $firstTokenMs, $collectedUsage, $collectedFinishReason, $actualModel);
                        $auditLogUpdated = true;  // 标记已更新

                        Log::debug('StreamHandler: 审计日志已更新（提前）', [
                            'audit_log_id' => $auditLog->id,
                            'latency_ms' => $latencyMs,
                            'first_token_ms' => $firstTokenMs,
                            'finish_reason' => $collectedFinishReason?->value,
                        ]);
                    }
                    yield $converted;
                }
            }
        } catch (\Exception $e) {
            // 流式迭代过程中的异常处理（包括客户端断开导致的 Generator throw）
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // 客户端断开连接不是错误，按成功处理
            $isClientDisconnect = str_contains($e->getMessage(), 'Client disconnected');
            $statusCode = $isClientDisconnect ? 200 : (method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500);

            // 提取已收集的模型名
            $actualModel = null;
            foreach ($streamChunks as $chunk) {
                if (! empty($chunk->model)) {
                    $actualModel = $chunk->model;
                    break;
                }
            }

            // 更新审计日志（包含已有的使用信息）
            $auditData = [
                'status_code' => $statusCode,
                'latency_ms' => $latencyMs,
            ];

            // 客户端断开时，不记录错误信息
            if (! $isClientDisconnect) {
                $auditData['error_type'] = get_class($e);
                $auditData['error_message'] = $e->getMessage();
            }

            // 如果有实际模型名，记录
            if ($actualModel !== null) {
                $auditData['actual_model'] = $actualModel;
            }

            // 如果有首字延迟，记录
            if ($firstTokenMs !== null) {
                $auditData['first_token_ms'] = $firstTokenMs;
            }

            // 如果有 token 使用信息，记录
            if ($collectedUsage !== null) {
                $auditData['prompt_tokens'] = $collectedUsage->inputTokens ?? 0;
                $auditData['completion_tokens'] = $collectedUsage->outputTokens ?? 0;
                $auditData['total_tokens'] = ($collectedUsage->inputTokens ?? 0) + ($collectedUsage->outputTokens ?? 0);
                $auditData['cache_read_tokens'] = $collectedUsage->cacheReadInputTokens ?? 0;
                $auditData['cache_write_tokens'] = $collectedUsage->cacheCreationInputTokens ?? 0;
            }

            if ($auditLog !== null) {
                $this->auditLogger->update($auditLog, $auditData);
                $auditLogUpdated = true;
                Log::debug('StreamHandler: 审计日志已更新（异常路径）', [
                    'audit_log_id' => $auditLog->id,
                    'status_code' => $statusCode,
                    'latency_ms' => $latencyMs,
                    'is_client_disconnect' => $isClientDisconnect,
                ]);
            }

            // 记录错误日志（客户端断开不记录错误）
            if (! $isClientDisconnect) {
                Log::error('Stream iteration failed', [
                    'request_id' => $auditLog?->request_id,
                    'channel_id' => $selectedChannel?->id,
                    'error' => $e->getMessage(),
                    'chunks_count' => count($streamChunks),
                    'latency_ms' => $latencyMs,
                ]);
            }

            // 客户端断开时不抛出异常，让 Generator 正常结束
            if (! $isClientDisconnect) {
                throw $e;
            }
        }

        // foreach 循环结束，Generator 后续代码开始执行
        Log::debug('StreamHandler: Generator foreach 结束，开始后续处理', [
            'chunks_count' => count($streamChunks),
            'has_finish_reason' => $collectedFinishReason !== null,
        ]);

        // 如果上游没有发送 finishReason（某些上游直接关闭连接），
        // 需要补充发送结束事件，否则客户端会认为响应不完整
        if ($collectedFinishReason === null) {
            Log::warning('StreamHandler: 上游未发送 finishReason，补充发送结束事件');
            $collectedFinishReason = FinishReason::Stop;

            // 构建一个补充的结束 chunk
            $endChunk = new StreamChunk;
            $endChunk->id = '';
            $endChunk->model = '';
            $endChunk->finishReason = FinishReason::Stop;
            $endChunk->usage = $collectedUsage;
            $endChunk->delta = '';
            $endChunk->contentDelta = null;
            $endChunk->reasoningDelta = null;

            yield $this->protocolConverter->convertStreamChunk($endChunk, $sourceProtocol);
        }

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        Log::debug('StreamHandler: 流式迭代完成，开始更新审计日志', [
            'latency_ms' => $latencyMs,
            'first_token_ms' => $firstTokenMs,
            'chunks_count' => count($streamChunks),
            'has_usage' => $collectedUsage !== null,
            'finish_reason' => $collectedFinishReason?->value,
        ]);

        // 注意：上游已经发送了 message_stop 事件，透传模式下不需要额外发送结束标记
        // yield $this->protocolConverter->driver($sourceProtocol)->buildStreamDone();

        // 流式结束后：调用协议后处理（如状态存储）
        if ($protocolContext !== null) {
            $response = $this->buildResponseFromChunks($streamChunks, $sourceProtocol);
            $response->postStreamProcess($streamChunks, $protocolContext);
        }

        // 提取实际模型名（从第一个有效的 chunk）
        $actualModel = null;
        foreach ($streamChunks as $chunk) {
            if (! empty($chunk->model)) {
                $actualModel = $chunk->model;
                break;
            }
        }

        // 更新审计日志（包含 token 使用信息和实际模型名）
        // 如果已经提前更新过，则跳过（避免重复更新）
        if (! $auditLogUpdated) {
            $this->updateAuditLogWithUsage($auditLog, $latencyMs, $firstTokenMs, $collectedUsage, $collectedFinishReason, $actualModel);
            Log::debug('StreamHandler: 审计日志已更新（正常）', [
                'audit_log_id' => $auditLog->id,
                'latency_ms' => $latencyMs,
                'first_token_ms' => $firstTokenMs,
                'finish_reason' => $collectedFinishReason?->value,
            ]);
        }

        // 记录渠道亲和性（成功请求后更新缓存）
        $this->recordAffinity($httpRequest, $modelName);

        // 从流式块中提取完整文本内容
        $generatedText = $this->extractTextFromChunks($streamChunks);

        // 组装完整的响应数据（用于记录 body_text）
        $completeResponse = $this->buildCompleteResponse($streamChunks, $modelName, $collectedUsage, $collectedFinishReason, $actualModel);

        // 记录响应日志
        $this->responseLogger->create(
            $requestLog,
            $completeResponse,
            null,
            $latencyMs,
            $auditLog->id,
            true,
            $collectedUsage,
            $collectedFinishReason,
            $generatedText,
            $streamChunks
        );

        // 更新渠道请求日志（记录上游返回的原始响应）
        if ($channelRequestLog !== null) {
            $this->updateChannelRequestLog(
                $channelRequestLog,
                $streamChunks,
                $latencyMs,
                $firstTokenMs,
                $collectedUsage,
                $collectedFinishReason,
                $actualModel
            );
        }
    }

    /**
     * 更新审计日志（包含 token 使用信息）
     */
    protected function updateAuditLogWithUsage(
        $auditLog,
        int $latencyMs,
        ?int $firstTokenMs,
        $usage,
        $finishReason,
        ?string $actualModel = null  // 新增：渠道响应的模型名
    ): void {
        $data = [
            'status_code' => 200,
            'latency_ms' => $latencyMs,
        ];

        // 首字延迟（只有非 null 才设置）
        if ($firstTokenMs !== null) {
            $data['first_token_ms'] = $firstTokenMs;
        }

        // 完成原因
        if ($finishReason !== null) {
            $data['finish_reason'] = $finishReason->value;
        }

        // 实际模型名（渠道响应返回的模型名）
        if ($actualModel !== null) {
            $data['actual_model'] = $actualModel;
        }

        // 更新 token 使用信息
        if ($usage !== null) {
            $data['prompt_tokens'] = $usage->inputTokens ?? 0;
            $data['completion_tokens'] = $usage->outputTokens ?? 0;
            $data['total_tokens'] = ($usage->inputTokens ?? 0) + ($usage->outputTokens ?? 0);
            $data['cache_read_tokens'] = $usage->cacheReadInputTokens ?? 0;
            $data['cache_write_tokens'] = $usage->cacheCreationInputTokens ?? 0;
        }

        $auditLog->update($data);

        Log::debug('StreamHandler: 审计日志已更新', [
            'audit_log_id' => $auditLog->id,
            'status_code' => $data['status_code'],
            'latency_ms' => $data['latency_ms'],
            'first_token_ms' => $firstTokenMs,
            'finish_reason' => $finishReason?->value,
        ]);
    }

    /**
     * 记录渠道亲和性
     */
    protected function recordAffinity(HttpRequest $request, string $model): void
    {
        if ($this->affinityService !== null) {
            $this->affinityService->recordAffinity($request, $this->selectedChannel ?? null, $model);
        }
    }

    /**
     * 从流式块中提取完整文本内容
     */
    protected function extractTextFromChunks(array $streamChunks): string
    {
        $text = '';

        foreach ($streamChunks as $chunk) {
            // 优先使用 content_delta
            if (! empty($chunk->contentDelta)) {
                $text .= $chunk->contentDelta;
            }
            // 兼容旧字段 delta
            elseif (! empty($chunk->delta)) {
                $text .= $chunk->delta;
            }
        }

        return $text;
    }

    /**
     * 从流式块组装完整的响应数据
     */
    protected function buildCompleteResponse(array $streamChunks, string $model, $usage, $finishReason, ?string $actualModel = null): array
    {
        // 提取 ID（从第一个有效的 chunk）
        $id = '';
        foreach ($streamChunks as $chunk) {
            if (! empty($chunk->id)) {
                $id = $chunk->id;
                break;
            }
        }

        // 如果未传入 actualModel，则从 streamChunks 中提取（向后兼容）
        if ($actualModel === null) {
            foreach ($streamChunks as $chunk) {
                if (! empty($chunk->model)) {
                    $actualModel = $chunk->model;
                    break;
                }
            }
        }

        // 提取完整文本
        $content = $this->extractTextFromChunks($streamChunks);

        // 提取推理内容
        $reasoningContent = '';
        foreach ($streamChunks as $chunk) {
            if (! empty($chunk->reasoningDelta)) {
                $reasoningContent .= $chunk->reasoningDelta;
            }
        }

        // 提取 tool_calls（从流式块中收集）
        $toolCalls = [];
        foreach ($streamChunks as $chunk) {
            if ($chunk->toolCalls !== null) {
                foreach ($chunk->toolCalls as $tc) {
                    $tcIndex = $tc['index'] ?? 0;
                    if (isset($tc['id']) && ! empty($tc['id'])) {
                        // 新工具调用开始
                        $toolCalls[$tcIndex] = $tc;
                    } elseif (isset($toolCalls[$tcIndex])) {
                        // 参数增量追加
                        if (isset($tc['function']['arguments'])) {
                            $toolCalls[$tcIndex]['function']['arguments'] .= $tc['function']['arguments'];
                        }
                    }
                }
            }
            // 兼容单个 toolCall 字段
            if ($chunk->toolCall !== null) {
                $tc = $chunk->toolCall;
                $tcIndex = $tc->index ?? 0;
                $tcId = $tc->id ?? '';
                if (! empty($tcId) && ! isset($toolCalls[$tcIndex])) {
                    $toolCalls[$tcIndex] = [
                        'id' => $tcId,
                        'type' => $tc->type ?? 'function',
                        'function' => [
                            'name' => $tc->name ?? '',
                            'arguments' => $tc->arguments ?? '',
                        ],
                    ];
                } elseif (isset($toolCalls[$tcIndex]) && ! empty($tc->arguments)) {
                    $toolCalls[$tcIndex]['function']['arguments'] .= $tc->arguments;
                }
            }
        }
        $toolCalls = array_values($toolCalls); // 重新索引

        // 构建标准 OpenAI 格式响应
        $response = [
            'id' => $id ?: 'chatcmpl-'.uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $actualModel ?? $model,  // 优先使用响应中的模型名
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $content,
                    ],
                    'finish_reason' => $finishReason?->value,
                ],
            ],
        ];

        // 添加推理内容（如果有）
        if ($reasoningContent) {
            $response['choices'][0]['message']['reasoning_content'] = $reasoningContent;
        }

        // 添加 tool_calls（如果有）
        if (! empty($toolCalls)) {
            $response['choices'][0]['message']['tool_calls'] = $toolCalls;
            // 有 tool_calls 时 content 不能为 null
            if ($response['choices'][0]['message']['content'] === null) {
                $response['choices'][0]['message']['content'] = '';
            }
        }

        // 添加 usage
        if ($usage !== null) {
            $response['usage'] = [
                'prompt_tokens' => $usage->inputTokens ?? 0,
                'completion_tokens' => $usage->outputTokens ?? 0,
                'total_tokens' => ($usage->inputTokens ?? 0) + ($usage->outputTokens ?? 0),
                'cache_read_tokens' => $usage->cacheReadInputTokens ?? 0,
                'cache_write_tokens' => $usage->cacheWriteInputTokens ?? 0,
            ];
        }

        return $response;
    }

    /**
     * 从流式块构建响应对象
     *
     * 用于调用 postStreamProcess()
     */
    protected function buildResponseFromChunks(array $chunks, string $protocol): ProtocolResponse
    {
        // 获取响应类
        $responseClass = $this->protocolConverter->getResponseClass($protocol);

        // 创建响应实例
        $response = new $responseClass;

        // 设置基本属性（从 chunks 提取）
        foreach ($chunks as $chunk) {
            if ($chunk instanceof StreamChunk) {
                if (! empty($chunk->id)) {
                    $response->id = $chunk->id;
                }
                if (! empty($chunk->model)) {
                    $response->model = $chunk->model;
                }
            }
        }

        return $response;
    }

    /**
     * 更新渠道请求日志（流式响应）
     */
    protected function updateChannelRequestLog(
        $channelRequestLog,
        array $streamChunks,
        int $latencyMs,
        ?int $firstTokenMs,
        $usage,
        $finishReason,
        ?string $actualModel
    ): void {
        // 构建完整的响应体（用于记录）
        $responseBody = [];
        foreach ($streamChunks as $chunk) {
            if ($chunk instanceof StreamChunk) {
                // 将每个chunk转换为数组格式
                $responseBody[] = [
                    'id' => $chunk->id ?? null,
                    'model' => $chunk->model ?? null,
                    'delta' => $chunk->delta ?? null,
                    'content_delta' => $chunk->contentDelta ?? null,
                    'reasoning_delta' => $chunk->reasoningDelta ?? null,
                    'tool_calls' => $chunk->toolCalls ?? null,
                    'finish_reason' => $chunk->finishReason?->value ?? null,
                    'usage' => $chunk->usage ? [
                        'input_tokens' => $chunk->usage->inputTokens ?? 0,
                        'output_tokens' => $chunk->usage->outputTokens ?? 0,
                        'cache_read_input_tokens' => $chunk->usage->cacheReadInputTokens ?? 0,
                        'cache_creation_input_tokens' => $chunk->usage->cacheCreationInputTokens ?? 0,
                    ] : null,
                ];
            }
        }

        // 计算响应大小
        $responseSize = strlen(json_encode($responseBody));

        // 更新渠道请求日志
        $updateData = [
            'response_status' => 200,
            'response_headers' => ['content-type' => 'text/event-stream'],
            'response_body' => $responseBody,
            'response_body_chunks' => $streamChunks,  // 保存原始chunk对象数组
            'response_size' => $responseSize,
            'latency_ms' => $latencyMs,
            'ttfb_ms' => $firstTokenMs,  // 首字延迟
            'is_success' => true,
        ];

        // 记录token使用量
        if ($usage !== null) {
            $updateData['usage'] = [
                'prompt_tokens' => $usage->inputTokens ?? 0,
                'completion_tokens' => $usage->outputTokens ?? 0,
                'total_tokens' => ($usage->inputTokens ?? 0) + ($usage->outputTokens ?? 0),
                'cache_read_tokens' => $usage->cacheReadInputTokens ?? 0,
                'cache_write_tokens' => $usage->cacheCreationInputTokens ?? 0,
            ];
        }

        $channelRequestLog->update($updateData);
    }
}
