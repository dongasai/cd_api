<?php

namespace App\Services\Shared\DTO;

use App\Services\Shared\Enums\ErrorType;
use App\Services\Shared\Enums\FinishReason;
use App\Services\Shared\Enums\StreamEventType;

/**
 * 统一流式块 DTO
 *
 * 纯数据容器，不包含业务逻辑
 */
class StreamChunk
{
    public function __construct(
        public string $id = '',
        public string $model = '',
        public ?string $contentDelta = null,
        public ?FinishReason $finishReason = null,
        public ?int $index = 0,
        public StreamEventType $type = StreamEventType::ContentDelta,
        public ?ToolCall $toolCall = null,
        public ?string $reasoningDelta = null,
        public ?string $signature = null,
        public ?Usage $usage = null,
        public ?string $rawEvent = null,
        public ?string $error = null,
        public ?ErrorType $errorType = null,
        public bool $isPartial = false,
        public ?string $parseError = null,
        // 兼容旧字段（临时保留）
        public string $event = '',
        public array $data = [],
        public string $delta = '',
        public ?array $toolCalls = null,
    ) {}

    /**
     * 是否为空数据块
     */
    public function isEmpty(): bool
    {
        // 有事件类型的不是空事件
        if (! empty($this->event)) {
            return false;
        }

        // 有推理内容的不是空事件
        if (! empty($this->reasoningDelta)) {
            return false;
        }

        // 有工具调用的不是空事件
        if (! empty($this->toolCalls)) {
            return false;
        }

        // 其他情况:检查是否有实际内容
        return empty($this->delta) && empty($this->finishReason) && empty($this->usage);
    }

    /**
     * 是否为结束数据块
     */
    public function isDone(): bool
    {
        return $this->event === 'message_stop' || $this->finishReason !== null;
    }

    /**
     * 转换为数组格式
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model,
            'content_delta' => $this->contentDelta,
            'finish_reason' => $this->finishReason?->value,
            'index' => $this->index,
            'type' => $this->type->value,
            'tool_call' => $this->toolCall?->toArray(),
            'reasoning_delta' => $this->reasoningDelta,
            'usage' => $this->usage?->toArray(),
            'event' => $this->event,
            'data' => $this->data,
            'delta' => $this->delta,
        ];
    }
}
