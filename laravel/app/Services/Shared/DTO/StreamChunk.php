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
    /**
     * 响应 ID
     */
    public string $id = '';

    /**
     * 模型名称
     */
    public string $model = '';

    /**
     * 内容增量
     */
    public ?string $contentDelta = null;

    /**
     * 结束原因
     */
    public ?FinishReason $finishReason = null;

    /**
     * 索引
     */
    public ?int $index = 0;

    /**
     * 事件类型
     */
    public StreamEventType $type = StreamEventType::ContentDelta;

    /**
     * 工具调用
     */
    public ?ToolCall $toolCall = null;

    /**
     * 推理增量
     */
    public ?string $reasoningDelta = null;

    /**
     * 签名
     */
    public ?string $signature = null;

    /**
     * 使用量
     */
    public ?Usage $usage = null;

    /**
     * 原始事件
     */
    public ?string $rawEvent = null;

    /**
     * 错误信息
     */
    public ?string $error = null;

    /**
     * 错误类型
     */
    public ?ErrorType $errorType = null;

    /**
     * 是否为部分数据
     */
    public bool $isPartial = false;

    /**
     * 解析错误
     */
    public ?string $parseError = null;

    /**
     * 事件名称（兼容旧字段）
     */
    public string $event = '';

    /**
     * 数据（兼容旧字段）
     */
    public array $data = [];

    /**
     * 增量（兼容旧字段）
     */
    public string $delta = '';

    /**
     * 工具调用列表（兼容旧字段）
     */
    public ?array $toolCalls = null;

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
}
