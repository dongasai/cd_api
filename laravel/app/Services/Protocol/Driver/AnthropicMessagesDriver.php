<?php

namespace App\Services\Protocol\Driver;

use App\Services\Protocol\DTO\StandardRequest;
use App\Services\Protocol\DTO\StandardResponse;
use App\Services\Protocol\DTO\StandardStreamEvent;
use App\Services\Protocol\DTO\StandardToolCall;
use App\Services\Protocol\DTO\StandardUsage;

/**
 * Anthropic Messages 协议驱动
 */
class AnthropicMessagesDriver extends AbstractDriver
{
    /**
     * 协议名称
     */
    public const PROTOCOL_NAME = 'anthropic_messages';

    /**
     * 支持的停止原因
     */
    public const STOP_REASONS = ['end_turn', 'max_tokens', 'stop_sequence', 'tool_use'];

    /**
     * 流式事件类型
     */
    public const EVENT_MESSAGE_START = 'message_start';

    public const EVENT_CONTENT_BLOCK_START = 'content_block_start';

    public const EVENT_CONTENT_BLOCK_DELTA = 'content_block_delta';

    public const EVENT_CONTENT_BLOCK_STOP = 'content_block_stop';

    public const EVENT_MESSAGE_DELTA = 'message_delta';

    public const EVENT_MESSAGE_STOP = 'message_stop';

    public const EVENT_ERROR = 'error';

    public const EVENT_PING = 'ping';

    /**
     * 获取协议名称
     */
    public function getProtocolName(): string
    {
        return self::PROTOCOL_NAME;
    }

    /**
     * 解析原始请求为标准格式
     */
    public function parseRequest(array $rawRequest): StandardRequest
    {
        return StandardRequest::fromAnthropic($rawRequest);
    }

    /**
     * 从标准格式构建 Anthropic 响应
     */
    public function buildResponse(StandardResponse $standardResponse): array
    {
        return $standardResponse->toAnthropic();
    }

    /**
     * 解析 Anthropic 流式事件
     */
    public function parseStreamEvent(string $rawEvent): ?StandardStreamEvent
    {
        $event = $this->parseSSEEvent($rawEvent);
        if ($event === null) {
            return null;
        }

        $eventType = $event['event'] ?? null;
        $data = $this->safeJsonDecode($event['data']);

        if ($data === null) {
            return null;
        }

        return match ($eventType) {
            self::EVENT_MESSAGE_START => $this->parseMessageStart($data),
            self::EVENT_CONTENT_BLOCK_START => $this->parseContentBlockStart($data),
            self::EVENT_CONTENT_BLOCK_DELTA => $this->parseContentBlockDelta($data),
            self::EVENT_MESSAGE_DELTA => $this->parseMessageDelta($data),
            self::EVENT_MESSAGE_STOP => StandardStreamEvent::finish(id: $this->currentId ?? ''),
            self::EVENT_ERROR => $this->parseError($data),
            self::EVENT_PING => $this->parsePingEvent($data),
            default => null,
        };
    }

    /**
     * 从标准格式构建 Anthropic 流式块
     */
    public function buildStreamChunk(StandardStreamEvent $event): string
    {
        return match ($event->type) {
            StandardStreamEvent::TYPE_START => $this->buildMessageStartEvent($event),
            StandardStreamEvent::TYPE_CONTENT_DELTA, StandardStreamEvent::TYPE_REASONING_DELTA => $this->buildContentBlockDeltaEvent($event),
            StandardStreamEvent::TYPE_TOOL_USE => $this->buildToolUseEvent($event),
            StandardStreamEvent::TYPE_FINISH => $this->buildMessageStopEvent($event),
            StandardStreamEvent::TYPE_ERROR => $this->buildErrorEvent($event),
            StandardStreamEvent::TYPE_PING => $this->buildPingEvent($event),
            StandardStreamEvent::TYPE_CONTENT_BLOCK_START => $this->buildContentBlockStartEvent($event),
            StandardStreamEvent::TYPE_MESSAGE_DELTA => $this->buildPassthroughEvent(self::EVENT_MESSAGE_DELTA, $event),
            StandardStreamEvent::TYPE_MESSAGE_STOP => $this->buildPassthroughEvent(self::EVENT_MESSAGE_STOP, $event),
            StandardStreamEvent::TYPE_CONTENT_BLOCK_STOP => $this->buildPassthroughEvent(self::EVENT_CONTENT_BLOCK_STOP, $event),
            default => '',
        };
    }

    /**
     * 构建流式结束标记
     */
    public function buildStreamDone(): string
    {
        return $this->buildSSEEvent(self::EVENT_MESSAGE_STOP, '{}');
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
            'type' => 'error',
            'error' => [
                'type' => $type,
                'message' => $message,
            ],
        ];
    }

    /**
     * 从标准请求构建上游请求
     */
    public function buildUpstreamRequest(StandardRequest $standardRequest): array
    {
        return $standardRequest->toAnthropic();
    }

    /**
     * 从上游响应解析标准响应
     */
    public function parseUpstreamResponse(array $response): StandardResponse
    {
        return StandardResponse::fromAnthropic($response);
    }

    // ==================== 流式事件解析 ====================

    /**
     * 当前消息ID (用于流式处理)
     */
    private string $currentId = '';

    /**
     * 当前内容块索引
     */
    private int $currentBlockIndex = 0;

    /**
     * 当前内容块类型（text/thinking）
     */
    private string $currentBlockType = 'text';

    /**
     * 是否已发送内容块开始事件
     */
    private bool $contentBlockStarted = false;

    /**
     * 当前工具调用索引（流式处理）
     */
    private ?int $currentToolCallIndex = null;

    /**
     * 是否已发送当前工具调用的开始事件
     */
    private bool $toolCallBlockStarted = false;

    /**
     * 解析消息开始事件
     */
    private function parseMessageStart(array $data): ?StandardStreamEvent
    {
        $message = $data['message'] ?? [];
        $this->currentId = $message['id'] ?? '';
        $this->currentBlockIndex = 0;
        $this->contentBlockStarted = false;
        $this->currentToolCallIndex = null;
        $this->toolCallBlockStarted = false;

        return StandardStreamEvent::start(
            id: $this->currentId,
            model: $message['model'] ?? '',
            role: $message['role'] ?? 'assistant'
        );
    }

    /**
     * 解析内容块开始事件
     */
    private function parseContentBlockStart(array $data): ?StandardStreamEvent
    {
        $index = $data['index'] ?? 0;
        $contentBlock = $data['content_block'] ?? [];
        $this->currentBlockIndex = $index;

        $blockType = $contentBlock['type'] ?? '';

        // 文本块开始，不产生事件
        if ($blockType === 'text') {
            return null;
        }

        // 思考块开始（thinking 类型），不产生事件
        if ($blockType === 'thinking') {
            return null;
        }

        // 工具调用块开始
        if ($blockType === 'tool_use') {
            $toolCall = new StandardToolCall(
                id: $contentBlock['id'] ?? '',
                type: 'function',
                name: $contentBlock['name'] ?? '',
                arguments: '',
                index: $index,
            );

            return StandardStreamEvent::toolUse($this->currentId, $toolCall);
        }

        return null;
    }

    /**
     * 解析内容块增量事件
     */
    private function parseContentBlockDelta(array $data): ?StandardStreamEvent
    {
        $index = $data['index'] ?? 0;
        $delta = $data['delta'] ?? [];

        $deltaType = $delta['type'] ?? '';

        // 文本增量
        if ($deltaType === 'text_delta') {
            $text = $delta['text'] ?? '';
            if ($text === '') {
                return null;
            }

            return StandardStreamEvent::delta($this->currentId, $text);
        }

        // 思考内容增量（thinking_delta）
        if ($deltaType === 'thinking_delta') {
            $thinking = $delta['thinking'] ?? '';
            if ($thinking === '') {
                return null;
            }

            return StandardStreamEvent::reasoningDelta($this->currentId, $thinking);
        }

        // 工具调用增量 (input_json_delta)
        if ($deltaType === 'input_json_delta') {
            $partialJson = $delta['partial_json'] ?? '';

            // 工具调用参数增量，暂不处理
            return null;
        }

        return null;
    }

    /**
     * 解析消息增量事件
     */
    private function parseMessageDelta(array $data): ?StandardStreamEvent
    {
        $delta = $data['delta'] ?? [];
        $usage = $data['usage'] ?? null;
        $stopReason = $delta['stop_reason'] ?? null;

        $standardUsage = null;
        if ($usage !== null) {
            $standardUsage = StandardUsage::fromAnthropic($usage);
        }

        return StandardStreamEvent::finish(
            id: $this->currentId,
            finishReason: $this->mapStopReason($stopReason),
            usage: $standardUsage
        );
    }

    /**
     * 解析错误事件
     */
    private function parseError(array $data): ?StandardStreamEvent
    {
        $error = $data['error'] ?? [];

        return StandardStreamEvent::error(
            id: $this->currentId,
            message: $error['message'] ?? 'Unknown error',
            type: $error['type'] ?? 'error'
        );
    }

    /**
     * 解析 ping 事件
     * ping 事件用于保持连接活跃，需要透传
     */
    private function parsePingEvent(array $data): ?StandardStreamEvent
    {
        // 创建一个特殊的 ping 事件，使用 TYPE_PING 类型
        return new StandardStreamEvent(
            type: StandardStreamEvent::TYPE_PING,
            id: $this->currentId,
            rawEvent: json_encode($data)
        );
    }

    // ==================== 流式事件构建 ====================

    /**
     * 构建消息开始事件
     */
    private function buildMessageStartEvent(StandardStreamEvent $event): string
    {
        $data = [
            'type' => self::EVENT_MESSAGE_START,
            'message' => [
                'id' => $event->id,
                'type' => 'message',
                'role' => $event->role ?? 'assistant',
                'model' => $event->model ?? '',
                'content' => [],
                'stop_reason' => null,
                'stop_sequence' => null,
                'usage' => [
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                ],
            ],
        ];

        return $this->buildSSEEvent(self::EVENT_MESSAGE_START, $this->safeJsonEncode($data));
    }

    /**
     * 构建内容块增量事件
     */
    private function buildContentBlockDeltaEvent(StandardStreamEvent $event): string
    {
        $output = '';

        // 判断是推理内容还是普通文本内容
        $isReasoning = $event->type === StandardStreamEvent::TYPE_REASONING_DELTA;
        $blockType = $isReasoning ? 'thinking' : 'text';
        $deltaType = $isReasoning ? 'thinking_delta' : 'text_delta';
        $content = $isReasoning ? $event->reasoningDelta : $event->contentDelta;

        // 如果内容为空，不发送任何事件
        if (empty($content)) {
            return '';
        }

        // 如果块类型发生变化，需要关闭旧块并开始新块
        if ($this->contentBlockStarted && $this->currentBlockType !== $blockType) {
            // 关闭旧块
            $blockStop = [
                'type' => self::EVENT_CONTENT_BLOCK_STOP,
                'index' => $this->currentBlockIndex,
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_STOP, $this->safeJsonEncode($blockStop));
            $this->contentBlockStarted = false;
            $this->currentBlockIndex++;
        }

        // 只在第一次发送内容块开始事件
        if (! $this->contentBlockStarted) {
            $blockStart = [
                'type' => self::EVENT_CONTENT_BLOCK_START,
                'index' => $this->currentBlockIndex,
                'content_block' => [
                    'type' => $blockType,
                    $blockType === 'thinking' ? 'thinking' : 'text' => '',
                ],
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_START, $this->safeJsonEncode($blockStart));
            $this->contentBlockStarted = true;
            $this->currentBlockType = $blockType;
        }

        // 内容增量
        $delta = [
            'type' => self::EVENT_CONTENT_BLOCK_DELTA,
            'index' => $this->currentBlockIndex,
            'delta' => [
                'type' => $deltaType,
                $isReasoning ? 'thinking' : 'text' => $content,
            ],
        ];
        $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_DELTA, $this->safeJsonEncode($delta));

        return $output;
    }

    /**
     * 构建工具使用事件
     */
    private function buildToolUseEvent(StandardStreamEvent $event): string
    {
        if ($event->toolCall === null) {
            return '';
        }

        $output = '';
        $toolIndex = $event->toolCall->index ?? 0;

        // 判断是否需要发送开始事件
        // 条件：有 id 和 name，且是第一次遇到这个工具调用
        $hasIdAndName = ! empty($event->toolCall->id) && ! empty($event->toolCall->name);
        $isFirstTime = $this->currentToolCallIndex !== $toolIndex;

        if ($hasIdAndName && $isFirstTime) {
            // 如果之前有内容块没关闭，先关闭
            if ($this->contentBlockStarted) {
                $blockStop = [
                    'type' => self::EVENT_CONTENT_BLOCK_STOP,
                    'index' => $this->currentBlockIndex,
                ];
                $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_STOP, $this->safeJsonEncode($blockStop));
                $this->contentBlockStarted = false;
                $this->currentBlockIndex++;
            }

            // 发送工具调用开始事件
            $blockStart = [
                'type' => self::EVENT_CONTENT_BLOCK_START,
                'index' => $this->currentBlockIndex,
                'content_block' => [
                    'type' => 'tool_use',
                    'id' => $event->toolCall->id,
                    'name' => $event->toolCall->name,
                    'input' => [],
                ],
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_START, $this->safeJsonEncode($blockStart));

            // 标记状态
            $this->currentToolCallIndex = $toolIndex;
            $this->toolCallBlockStarted = true;
            $this->contentBlockStarted = false; // 工具调用不是内容块
        }

        // 发送参数增量事件
        if (! empty($event->toolCall->arguments)) {
            $blockDelta = [
                'type' => self::EVENT_CONTENT_BLOCK_DELTA,
                'index' => $this->currentBlockIndex,
                'delta' => [
                    'type' => 'input_json_delta',
                    'partial_json' => $event->toolCall->arguments,
                ],
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_DELTA, $this->safeJsonEncode($blockDelta));
        }

        return $output;
    }

    /**
     * 构建消息停止事件
     */
    private function buildMessageStopEvent(StandardStreamEvent $event): string
    {
        $output = '';

        // 发送内容块停止事件（如果有未关闭的内容块或工具调用块）
        if ($this->contentBlockStarted || $this->toolCallBlockStarted) {
            $blockStop = [
                'type' => self::EVENT_CONTENT_BLOCK_STOP,
                'index' => $this->currentBlockIndex,
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_STOP, $this->safeJsonEncode($blockStop));
            $this->contentBlockStarted = false;
            $this->toolCallBlockStarted = false;
        }

        // 消息增量
        $delta = [
            'type' => self::EVENT_MESSAGE_DELTA,
            'delta' => [
                'stop_reason' => $this->mapFinishReason($event->finishReason),
            ],
            'usage' => $event->usage ? $event->usage->toAnthropic() : ['output_tokens' => 0],
        ];
        $output .= $this->buildSSEEvent(self::EVENT_MESSAGE_DELTA, $this->safeJsonEncode($delta));

        // 消息停止
        $output .= $this->buildSSEEvent(self::EVENT_MESSAGE_STOP, '{}');

        return $output;
    }

    /**
     * 构建 ping 事件
     */
    private function buildPingEvent(StandardStreamEvent $event): string
    {
        return $this->buildSSEEvent(self::EVENT_PING, $event->rawEvent ?? '{}');
    }

    /**
     * 构建内容块开始事件
     */
    private function buildContentBlockStartEvent(StandardStreamEvent $event): string
    {
        // 如果有原始事件数据,直接透传
        if ($event->rawEvent !== null) {
            $parsed = json_decode($event->rawEvent, true);
            if ($parsed !== null) {
                return $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_START, $this->safeJsonEncode($parsed));
            }
        }

        // 否则构建默认的事件
        return '';
    }

    /**
     * 构建透传事件 (直接使用原始数据)
     */
    private function buildPassthroughEvent(string $eventType, StandardStreamEvent $event): string
    {
        // 如果有原始事件数据,直接透传
        if ($event->rawEvent !== null) {
            $parsed = json_decode($event->rawEvent, true);
            if ($parsed !== null) {
                return $this->buildSSEEvent($eventType, $this->safeJsonEncode($parsed));
            }
        }

        // 没有原始数据,返回空
        return '';
    }

    /**
     * 重置流式状态
     */
    private function resetStreamState(): void
    {
        $this->currentId = '';
        $this->currentBlockIndex = 0;
        $this->currentBlockType = 'text';
        $this->contentBlockStarted = false;
        $this->currentToolCallIndex = null;
        $this->toolCallBlockStarted = false;
    }

    /**
     * 构建错误事件
     */
    private function buildErrorEvent(StandardStreamEvent $event): string
    {
        $data = [
            'type' => 'error',
            'error' => [
                'type' => $event->errorType ?? 'error',
                'message' => $event->errorMessage ?? 'Unknown error',
            ],
        ];

        return $this->buildSSEEvent(self::EVENT_ERROR, $this->safeJsonEncode($data));
    }

    // ==================== 辅助方法 ====================

    /**
     * 映射停止原因到标准格式
     */
    private function mapStopReason(?string $stopReason): ?string
    {
        return match ($stopReason) {
            'end_turn' => 'stop',
            'max_tokens' => 'length',
            'stop_sequence' => 'stop',
            'tool_use' => 'tool_calls',
            null => null,
            default => $stopReason,
        };
    }

    /**
     * 映射结束原因到 Anthropic 格式
     */
    private function mapFinishReason(?string $finishReason): ?string
    {
        return match ($finishReason) {
            'stop' => 'end_turn',
            'length' => 'max_tokens',
            'tool_calls' => 'tool_use',
            'content_filter' => 'end_turn',
            null => null,
            default => $finishReason,
        };
    }
}
