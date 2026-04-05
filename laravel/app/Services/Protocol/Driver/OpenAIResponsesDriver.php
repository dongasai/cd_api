<?php

namespace App\Services\Protocol\Driver;

use App\Services\Protocol\Contracts\ProtocolRequest;
use App\Services\Protocol\Contracts\ProtocolResponse;
use App\Services\Protocol\Driver\OpenAI\ChatCompletionResponse;
use App\Services\Protocol\Driver\OpenAIResponses\OpenAIResponsesRequest;
use App\Services\Protocol\Driver\OpenAIResponses\OpenAIResponsesResponse;
use App\Services\Shared\DTO\StreamChunk;
use App\Services\Shared\Enums\FinishReason;

/**
 * OpenAI Responses API 协议驱动
 *
 * 无状态驱动，仅负责协议格式转换
 * 状态管理由 OpenAIResponsesRequest/Response 的双向契约处理
 */
class OpenAIResponsesDriver extends AbstractDriver
{
    /**
     * 协议名称
     */
    public const PROTOCOL_NAME = 'openai_responses';

    /**
     * 流式事件序列号（流式格式转换需要）
     */
    protected int $sequenceNumber = 0;

    /**
     * 是否已发送初始化事件
     */
    protected bool $initialized = false;

    /**
     * 当前响应 ID
     */
    protected string $currentResponseId = '';

    /**
     * 当前工具调用 ID（用于流式 tool_calls）
     */
    protected string $currentToolCallId = '';

    /**
     * 是否已发送 function_call 开始事件
     */
    protected bool $functionCallStarted = false;

    /**
     * 累积的输出项目（用于构建最终响应）
     */
    protected array $outputItems = [];

    /**
     * 当前正在处理的输出项目类型（message 或 function_call）
     */
    protected string $currentItemType = '';

    /**
     * 是否已发送 response.created 事件
     */
    protected bool $responseCreatedSent = false;

    /**
     * 是否已发送 output_item 初始化事件
     */
    protected bool $outputItemInitialized = false;

    /**
     * 累积的文本内容（用于构建最终响应）
     */
    protected string $accumulatedText = '';

    /**
     * 获取协议名称
     */
    public function getProtocolName(): string
    {
        return self::PROTOCOL_NAME;
    }

    /**
     * 解析原始请求为协议请求结构体
     */
    public function parseRequest(array $rawRequest): ProtocolRequest
    {
        return OpenAIResponsesRequest::fromArrayValidated($rawRequest);
    }

    /**
     * 从协议响应结构体构建 Responses 响应数组
     */
    public function buildResponse(ProtocolResponse $response): array
    {
        // 如果是 ChatCompletionResponse，需要转换为 OpenAIResponsesResponse
        if ($response instanceof ChatCompletionResponse) {
            $responsesResponse = OpenAIResponsesResponse::fromChatCompletions($response);
        } elseif ($response instanceof OpenAIResponsesResponse) {
            $responsesResponse = $response;
        } else {
            // 其他类型，通过 SharedDTO 转换
            $sharedDTO = $response->toSharedDTO();
            $responsesResponse = OpenAIResponsesResponse::fromSharedDTO($sharedDTO);
        }

        return $responsesResponse->toArray();
    }

    /**
     * 从标准格式构建 Responses 流式块
     */
    public function buildStreamChunk(StreamChunk $chunk): string
    {
        // 处理错误
        if ($chunk->error !== null) {
            return $this->safeJsonEncode([
                'type' => 'error',
                'error' => [
                    'message' => $chunk->error,
                    'type' => $chunk->errorType?->value ?? 'error',
                ],
            ]);
        }

        // 生成响应 ID
        $responseId = $chunk->id ?: 'resp_'.uniqid();
        if ($this->currentResponseId === '') {
            $this->currentResponseId = $responseId;
        }

        // 发送 response.created 事件（仅一次）
        $responseCreatedEvent = '';
        if (! $this->responseCreatedSent) {
            $this->responseCreatedSent = true;
            $responseCreatedEvent = $this->buildResponseCreatedEvent($responseId, $chunk->model);
        }

        // 完成事件
        if ($chunk->finishReason !== null) {
            return $responseCreatedEvent.$this->buildCompleteEvents($responseId, $chunk);
        }

        // 检查是否有工具调用（优先处理）
        if (! empty($chunk->toolCalls)) {
            // 如果还没有初始化 output_item，发送 function_call 初始化
            if (! $this->outputItemInitialized) {
                $this->outputItemInitialized = true;
                $this->currentItemType = 'function_call';
                $toolEvents = $this->buildToolCallEvents($chunk->toolCalls, $responseId);

                return $responseCreatedEvent.$toolEvents;
            }

            // 后续的 tool_calls
            return $this->buildToolCallEvents($chunk->toolCalls, $responseId);
        }

        // 文本增量事件
        $contentDelta = $chunk->contentDelta ?? $chunk->delta ?? '';
        if ($contentDelta !== '' && $contentDelta !== null) {
            // 如果还没有初始化 output_item，发送 message 初始化
            if (! $this->outputItemInitialized) {
                $this->outputItemInitialized = true;
                $this->currentItemType = 'message';
                $messageInit = $this->buildMessageItemInitEvents($responseId);
                $deltaEvent = $this->buildDeltaEvent($contentDelta, $responseId);

                return $responseCreatedEvent.$messageInit.$deltaEvent;
            }

            return $this->buildDeltaEvent($contentDelta, $responseId);
        }

        // 推理内容事件（思考模型）
        if ($chunk->reasoningDelta !== null && $chunk->reasoningDelta !== '') {
            // 跳过推理事件，避免 SDK 不兼容
            return $responseCreatedEvent;
        }

        // 默认返回空事件
        return $responseCreatedEvent;
    }

    /**
     * 构建响应创建事件（通用）
     */
    protected function buildResponseCreatedEvent(string $responseId, string $model): string
    {
        return 'data: '.$this->safeJsonEncode([
            'type' => 'response.created',
            'sequence_number' => $this->sequenceNumber++,
            'response' => [
                'id' => $responseId,
                'object' => 'response',
                'created_at' => time(),
                'model' => $model,
                'status' => 'in_progress',
                'output' => [],
                'tool_choice' => 'auto',
                'tools' => [],
                'parallel_tool_calls' => false,
            ],
        ])."\n\n";
    }

    /**
     * 构建消息类型 output_item 初始化事件
     */
    protected function buildMessageItemInitEvents(string $responseId): string
    {
        $events = [];

        // 1. response.output_item.added (message 类型)
        $itemId = $responseId.'_msg';
        $this->outputItems[] = [
            'id' => $itemId,
            'type' => 'message',
            'status' => 'in_progress',
        ];
        $events[] = 'data: '.$this->safeJsonEncode([
            'type' => 'response.output_item.added',
            'sequence_number' => $this->sequenceNumber++,
            'output_index' => 0,
            'response_id' => $responseId,
            'item' => [
                'id' => $itemId,
                'type' => 'message',
                'status' => 'in_progress',
                'role' => 'assistant',
                'content' => [],
            ],
        ])."\n\n";

        // 2. response.content_part.added
        $events[] = 'data: '.$this->safeJsonEncode([
            'type' => 'response.content_part.added',
            'sequence_number' => $this->sequenceNumber++,
            'output_index' => 0,
            'content_index' => 0,
            'part' => [
                'type' => 'output_text',
                'text' => '',
                'annotations' => [],
            ],
        ])."\n\n";

        return implode('', $events);
    }

    /**
     * 构建消息类型初始化事件序列（包含 response.created）
     *
     * @deprecated 使用 buildResponseCreatedEvent + buildMessageItemInitEvents 代替
     */
    protected function buildMessageInitEvents(string $responseId, string $model): string
    {
        $events = [];

        // 1. response.created
        $events[] = $this->buildResponseCreatedEvent($responseId, $model);

        // 2. message output_item 初始化
        $events[] = $this->buildMessageItemInitEvents($responseId);

        return implode('', $events);
    }

    /**
     * 构建文本增量事件
     */
    protected function buildDeltaEvent(string $contentDelta, string $responseId): string
    {
        // 累积文本内容
        $this->accumulatedText .= $contentDelta;

        return 'data: '.$this->safeJsonEncode([
            'type' => 'response.output_text.delta',
            'delta' => $contentDelta,
            'output_index' => 0,
            'content_index' => 0,
            'item_id' => $responseId.'_msg',
            'sequence_number' => $this->sequenceNumber++,
        ])."\n\n";
    }

    /**
     * 构建工具调用事件
     */
    protected function buildToolCallEvents(array $toolCalls, string $responseId): string
    {
        $events = [];

        foreach ($toolCalls as $toolCall) {
            $toolCallId = $toolCall['id'] ?? $this->currentToolCallId;
            $functionName = $toolCall['function']['name'] ?? '';
            $functionArgs = $toolCall['function']['arguments'] ?? '';

            // 如果有新的 tool_call ID，发送 function_call 开始事件
            if (! empty($toolCall['id']) && $toolCall['id'] !== $this->currentToolCallId) {
                $this->currentToolCallId = $toolCall['id'];
                $this->functionCallStarted = true;

                // 累积输出项目
                $outputIndex = count($this->outputItems);
                // call_id 是工具调用的唯一标识符，客户端使用它来引用和执行工具调用
                $callId = $toolCallId;
                $this->outputItems[] = [
                    'id' => $toolCallId,
                    'call_id' => $callId,
                    'type' => 'function_call',
                    'status' => 'in_progress',
                    'name' => $functionName,
                    'arguments' => '', // 初始为空，后续追加
                ];

                // 发送 function_call_item.added 事件
                $events[] = 'data: '.$this->safeJsonEncode([
                    'type' => 'response.output_item.added',
                    'sequence_number' => $this->sequenceNumber++,
                    'output_index' => $outputIndex,
                    'response_id' => $responseId,
                    'item' => [
                        'id' => $toolCallId,
                        'call_id' => $callId,
                        'type' => 'function_call',
                        'status' => 'in_progress',
                        'name' => $functionName,
                        'arguments' => '',
                    ],
                ])."\n\n";
            }

            // 发送参数增量事件
            if (! empty($functionArgs) && $this->functionCallStarted) {
                // 累积 arguments（追加而非覆盖）
                $lastIndex = count($this->outputItems) - 1;
                if ($lastIndex >= 0) {
                    if (! isset($this->outputItems[$lastIndex]['arguments'])) {
                        $this->outputItems[$lastIndex]['arguments'] = '';
                    }
                    $this->outputItems[$lastIndex]['arguments'] .= $functionArgs;
                }

                $events[] = 'data: '.$this->safeJsonEncode([
                    'type' => 'response.function_call_arguments.delta',
                    'sequence_number' => $this->sequenceNumber++,
                    'output_index' => count($this->outputItems) - 1,
                    'item_id' => $toolCallId,
                    'delta' => $functionArgs,
                ])."\n\n";
            }
        }

        return implode('', $events);
    }

    /**
     * 构建完成事件序列
     */
    protected function buildCompleteEvents(string $responseId, StreamChunk $chunk): string
    {
        $events = [];
        $stopReason = $this->mapFinishReason($chunk->finishReason->value);

        // 如果是工具调用结束，需要先完成所有 message 项目
        if ($chunk->finishReason->value === 'tool_use' || $chunk->finishReason === FinishReason::ToolUse) {
            // 先完成 message 类型的项目
            foreach ($this->outputItems as $outputIndex => $outputItem) {
                if ($outputItem['type'] === 'message') {
                    // 更新状态为 completed
                    $this->outputItems[$outputIndex]['status'] = 'completed';

                    // 发送 output_text.done
                    $events[] = 'data: '.$this->safeJsonEncode([
                        'type' => 'response.output_text.done',
                        'sequence_number' => $this->sequenceNumber++,
                        'output_index' => $outputIndex,
                        'content_index' => 0,
                        'text' => $this->accumulatedText,
                    ])."\n\n";

                    // 发送 content_part.done
                    $events[] = 'data: '.$this->safeJsonEncode([
                        'type' => 'response.content_part.done',
                        'sequence_number' => $this->sequenceNumber++,
                        'output_index' => $outputIndex,
                        'content_index' => 0,
                        'part' => [
                            'type' => 'output_text',
                            'text' => $this->accumulatedText,
                            'annotations' => [],
                        ],
                    ])."\n\n";

                    // 发送 output_item.done
                    $events[] = 'data: '.$this->safeJsonEncode([
                        'type' => 'response.output_item.done',
                        'sequence_number' => $this->sequenceNumber++,
                        'output_index' => $outputIndex,
                        'response_id' => $responseId,
                        'item' => [
                            'id' => $outputItem['id'],
                            'type' => 'message',
                            'status' => 'completed',
                            'role' => 'assistant',
                            'content' => [
                                ['type' => 'output_text', 'text' => $this->accumulatedText, 'annotations' => []],
                            ],
                        ],
                    ])."\n\n";
                }
            }

            // 然后完成 function_call 类型的项目
            foreach ($this->outputItems as $outputIndex => $outputItem) {
                if ($outputItem['type'] === 'function_call') {
                    // 更新状态为 completed
                    $this->outputItems[$outputIndex]['status'] = 'completed';

                    $events[] = 'data: '.$this->safeJsonEncode([
                        'type' => 'response.function_call_arguments.done',
                        'sequence_number' => $this->sequenceNumber++,
                        'output_index' => $outputIndex,
                        'item_id' => $outputItem['id'],
                        'arguments' => $outputItem['arguments'] ?? '',
                    ])."\n\n";

                    $events[] = 'data: '.$this->safeJsonEncode([
                        'type' => 'response.output_item.done',
                        'sequence_number' => $this->sequenceNumber++,
                        'output_index' => $outputIndex,
                        'response_id' => $responseId,
                        'item' => [
                            'id' => $outputItem['id'],
                            'call_id' => $outputItem['call_id'] ?? $outputItem['id'],
                            'type' => 'function_call',
                            'status' => 'completed',
                            'name' => $outputItem['name'] ?? '',
                            'arguments' => $outputItem['arguments'] ?? '',
                        ],
                    ])."\n\n";
                }
            }
        } elseif ($this->currentItemType === 'message') {
            // 正常文本结束
            // 1. response.output_text.done
            $events[] = 'data: '.$this->safeJsonEncode([
                'type' => 'response.output_text.done',
                'sequence_number' => $this->sequenceNumber++,
                'output_index' => 0,
                'content_index' => 0,
                'text' => $this->accumulatedText,
            ])."\n\n";

            // 2. response.content_part.done
            $events[] = 'data: '.$this->safeJsonEncode([
                'type' => 'response.content_part.done',
                'sequence_number' => $this->sequenceNumber++,
                'output_index' => 0,
                'content_index' => 0,
                'part' => [
                    'type' => 'output_text',
                    'text' => $this->accumulatedText,
                    'annotations' => [],
                ],
            ])."\n\n";

            // 3. response.output_item.done
            $events[] = 'data: '.$this->safeJsonEncode([
                'type' => 'response.output_item.done',
                'sequence_number' => $this->sequenceNumber++,
                'output_index' => 0,
                'response_id' => $responseId,
                'item' => [
                    'id' => $responseId.'_msg',
                    'type' => 'message',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => $this->accumulatedText, 'annotations' => []],
                    ],
                ],
            ])."\n\n";
        }

        // 4. response.completed（包含完整的 output）
        $output = [];

        // 如果没有任何输出项目，创建一个空的 message
        if (empty($this->outputItems) && empty($this->accumulatedText)) {
            $this->outputItems[] = [
                'id' => $responseId.'_msg',
                'type' => 'message',
                'status' => 'completed',
            ];
            $this->currentItemType = 'message';

            // 发送空的 message output_item.done 事件
            $events[] = 'data: '.$this->safeJsonEncode([
                'type' => 'response.output_item.done',
                'sequence_number' => $this->sequenceNumber++,
                'output_index' => 0,
                'response_id' => $responseId,
                'item' => [
                    'id' => $responseId.'_msg',
                    'type' => 'message',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [],
                ],
            ])."\n\n";
        }

        foreach ($this->outputItems as $outputItem) {
            if ($outputItem['type'] === 'message') {
                // message 类型需要包含 role 和 content
                $output[] = [
                    'id' => $outputItem['id'],
                    'type' => 'message',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => $this->accumulatedText, 'annotations' => []],
                    ],
                ];
            } else {
                // function_call 类型
                $output[] = [
                    'id' => $outputItem['id'],
                    'call_id' => $outputItem['call_id'] ?? $outputItem['id'],
                    'type' => 'function_call',
                    'status' => 'completed',
                    'name' => $outputItem['name'] ?? '',
                    'arguments' => $outputItem['arguments'] ?? '',
                ];
            }
        }

        $result = [
            'type' => 'response.completed',
            'sequence_number' => $this->sequenceNumber++,
            'response' => [
                'id' => $responseId,
                'object' => 'response',
                'created' => time(),
                'created_at' => time(),
                'model' => $chunk->model,
                'status' => 'completed',
                'output' => $output,
                'tool_choice' => 'auto',
                'tools' => [],
                'parallel_tool_calls' => false,
                'store' => true,
                'metadata' => [],
                'stop_reason' => $stopReason,
            ],
        ];

        // 添加 usage
        if ($chunk->usage !== null) {
            $result['response']['usage'] = [
                'input_tokens' => $chunk->usage->inputTokens,
                'output_tokens' => $chunk->usage->outputTokens,
                'total_tokens' => $chunk->usage->inputTokens + $chunk->usage->outputTokens,
                'input_tokens_details' => [
                    'cached_tokens' => 0,
                ],
                'output_tokens_details' => [
                    'reasoning_tokens' => 0,
                ],
            ];
        }

        $events[] = 'data: '.$this->safeJsonEncode($result)."\n\n";

        return implode('', $events);
    }

    /**
     * 构建流式结束标记
     */
    public function buildStreamDone(): string
    {
        return "data: [DONE]\n\n";
    }

    /**
     * 获取请求中的模型名称
     */
    public function extractModel(array $rawRequest): string
    {
        return $rawRequest['model'] ?? '';
    }

    /**
     * 构建错误响应
     */
    public function buildErrorResponse(string $message, string $type = 'error', int $code = 500): array
    {
        return [
            'error' => [
                'message' => $message,
                'type' => $type,
                'code' => $code,
            ],
        ];
    }

    /**
     * 映射 finish_reason → stop_reason
     */
    protected function mapFinishReason(string $finishReason): string
    {
        return match ($finishReason) {
            'stop', 'end_turn' => 'end_turn',
            'max_tokens', 'length' => 'max_tokens',
            'tool_use', 'tool_calls' => 'tool_use',
            'content_filter' => 'content_filter',
            default => $finishReason,
        };
    }
}
