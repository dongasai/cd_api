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
            StandardStreamEvent::TYPE_CONTENT_DELTA => $this->buildContentBlockDeltaEvent($event),
            StandardStreamEvent::TYPE_TOOL_USE => $this->buildToolUseEvent($event),
            StandardStreamEvent::TYPE_FINISH => $this->buildMessageStopEvent($event),
            StandardStreamEvent::TYPE_ERROR => $this->buildErrorEvent($event),
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
     * 是否已发送内容块开始事件
     */
    private bool $contentBlockStarted = false;

    /**
     * 解析消息开始事件
     */
    private function parseMessageStart(array $data): ?StandardStreamEvent
    {
        $message = $data['message'] ?? [];
        $this->currentId = $message['id'] ?? '';
        $this->currentBlockIndex = 0;
        $this->contentBlockStarted = false;

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

        // 文本块开始，不产生事件
        if (($contentBlock['type'] ?? '') === 'text') {
            return null;
        }

        // 工具调用块开始
        if (($contentBlock['type'] ?? '') === 'tool_use') {
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

        // 文本增量
        if (($delta['type'] ?? '') === 'text_delta') {
            $text = $delta['text'] ?? '';
            if ($text === '') {
                return null;
            }

            return StandardStreamEvent::delta($this->currentId, $text);
        }

        // 工具调用增量 (input_json_delta)
        if (($delta['type'] ?? '') === 'input_json_delta') {
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

        // 只在第一次发送内容块开始事件
        if (! $this->contentBlockStarted) {
            $blockStart = [
                'type' => self::EVENT_CONTENT_BLOCK_START,
                'index' => 0,
                'content_block' => [
                    'type' => 'text',
                    'text' => '',
                ],
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_START, $this->safeJsonEncode($blockStart));
            $this->contentBlockStarted = true;
        }

        // 内容增量
        $delta = [
            'type' => self::EVENT_CONTENT_BLOCK_DELTA,
            'index' => 0,
            'delta' => [
                'type' => 'text_delta',
                'text' => $event->contentDelta,
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

        // 内容块开始 (tool_use)
        $blockStart = [
            'type' => self::EVENT_CONTENT_BLOCK_START,
            'index' => $event->toolCall->index ?? 0,
            'content_block' => [
                'type' => 'tool_use',
                'id' => $event->toolCall->id,
                'name' => $event->toolCall->name,
                'input' => [],
            ],
        ];
        $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_START, $this->safeJsonEncode($blockStart));

        return $output;
    }

    /**
     * 构建消息停止事件
     */
    private function buildMessageStopEvent(StandardStreamEvent $event): string
    {
        $output = '';

        // 发送内容块停止事件
        if ($this->contentBlockStarted) {
            $blockStop = [
                'type' => self::EVENT_CONTENT_BLOCK_STOP,
                'index' => 0,
            ];
            $output .= $this->buildSSEEvent(self::EVENT_CONTENT_BLOCK_STOP, $this->safeJsonEncode($blockStop));
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
