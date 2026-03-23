<?php

namespace App\Services\Router\Handler;

use App\Models\RequestLog;
use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\ProviderManager;
use App\Services\Router\Logger\AuditLogger;
use App\Services\Router\Logger\ResponseLogger;
use App\Services\Shared\DTO\StreamChunk;
use Generator;
use Illuminate\Http\Request as HttpRequest;

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
        $this->affinityService = $affinityService ?? app(\App\Services\ChannelAffinity\ChannelAffinityService::class);
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
        $selectedChannel = null  // 接收选中的渠道
    ): Generator {
        $this->selectedChannel = $selectedChannel;  // 保存渠道引用，用于记录亲和性

        // 获取模型名称
        $modelName = $protocolRequest->getModel();

        $stream = $provider->sendStream($protocolRequest);

        // 使用传入的审计日志（如果已创建），否则创建新的
        if ($auditLog === null) {
            $auditLog = $this->auditLogger->createInitial($httpRequest, $modelName, true, $sourceProtocol);
        }

        $firstTokenMs = null;
        $streamChunks = [];
        $collectedUsage = null;
        $collectedFinishReason = null;

        // 检查是否需要过滤 thinking 内容
        $shouldFilterThinking = $selectedChannel !== null && $selectedChannel->shouldFilterThinking();

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

                // 收集流式块
                $streamChunks[] = $chunk->toArray();

                // 收集 usage（来自最后一个有 usage 的 chunk）
                if ($chunk->usage !== null) {
                    $collectedUsage = $chunk->usage;
                }

                // 收集 finishReason
                if ($chunk->finishReason !== null) {
                    $collectedFinishReason = $chunk->finishReason;
                }

                // 转换并输出
                yield $this->protocolConverter->convertStreamChunk($chunk, $sourceProtocol);
            }
        }

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        // 注意：上游已经发送了 message_stop 事件，透传模式下不需要额外发送结束标记
        // yield $this->protocolConverter->driver($sourceProtocol)->buildStreamDone();

        // 更新审计日志（包含 token 使用信息）
        $this->updateAuditLogWithUsage($auditLog, $latencyMs, $firstTokenMs, $collectedUsage, $collectedFinishReason);

        // 记录渠道亲和性（成功请求后更新缓存）
        $this->recordAffinity($httpRequest, $modelName);

        // 从流式块中提取完整文本内容
        $generatedText = $this->extractTextFromChunks($streamChunks);

        // 组装完整的响应数据（用于记录 body_text）
        $completeResponse = $this->buildCompleteResponse($streamChunks, $modelName, $collectedUsage, $collectedFinishReason);

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
    }

    /**
     * 更新审计日志（包含 token 使用信息）
     */
    protected function updateAuditLogWithUsage(
        $auditLog,
        int $latencyMs,
        ?int $firstTokenMs,
        $usage,
        $finishReason
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

        // 更新 token 使用信息
        if ($usage !== null) {
            $data['prompt_tokens'] = $usage->inputTokens ?? 0;
            $data['completion_tokens'] = $usage->outputTokens ?? 0;
            $data['total_tokens'] = ($usage->inputTokens ?? 0) + ($usage->outputTokens ?? 0);
            $data['cache_read_tokens'] = $usage->cacheReadInputTokens ?? 0;
            $data['cache_write_tokens'] = $usage->cacheCreationInputTokens ?? 0;
        }

        $auditLog->update($data);
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
            if (! empty($chunk['content_delta'])) {
                $text .= $chunk['content_delta'];
            }
            // 兼容旧字段 delta
            elseif (! empty($chunk['delta'])) {
                $text .= $chunk['delta'];
            }
        }

        return $text;
    }

    /**
     * 从流式块组装完整的响应数据
     */
    protected function buildCompleteResponse(array $streamChunks, string $model, $usage, $finishReason): array
    {
        // 提取 ID（从第一个有效的 chunk）
        $id = '';
        foreach ($streamChunks as $chunk) {
            if (! empty($chunk['id'])) {
                $id = $chunk['id'];
                break;
            }
        }

        // 提取完整文本
        $content = $this->extractTextFromChunks($streamChunks);

        // 提取推理内容
        $reasoningContent = '';
        foreach ($streamChunks as $chunk) {
            if (! empty($chunk['reasoning_delta'])) {
                $reasoningContent .= $chunk['reasoning_delta'];
            }
        }

        // 构建标准 OpenAI 格式响应
        $response = [
            'id' => $id ?: 'chatcmpl-'.uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $model,
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
}
