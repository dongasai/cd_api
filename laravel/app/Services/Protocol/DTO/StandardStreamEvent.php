<?php

namespace App\Services\Protocol\DTO;

/**
 * 标准流式事件 DTO
 */
class StandardStreamEvent
{
    // 事件类型常量
    public const TYPE_START = 'start';           // 流开始

    public const TYPE_CONTENT_DELTA = 'delta';   // 内容增量

    public const TYPE_TOOL_USE = 'tool_use';     // 工具调用

    public const TYPE_FINISH = 'finish';         // 流结束

    public const TYPE_ERROR = 'error';           // 错误

    public function __construct(
        // 事件类型
        public string $type,

        // 响应ID
        public string $id,

        // 模型名称
        public ?string $model = null,

        // 内容增量
        public ?string $contentDelta = null,

        // 角色信息 (start 事件)
        public ?string $role = null,

        // 工具调用信息
        public ?StandardToolCall $toolCall = null,

        // 结束原因 (finish 事件)
        public ?string $finishReason = null,

        // Token 使用量 (finish 事件)
        public ?StandardUsage $usage = null,

        // 错误信息 (error 事件)
        public ?string $errorMessage = null,
        public ?string $errorType = null,

        // 原始事件数据
        public ?string $rawEvent = null,

        // 创建时间戳
        public int $created = 0,
    ) {}

    /**
     * 创建开始事件
     */
    public static function start(string $id, string $model, string $role = 'assistant'): self
    {
        return new self(
            type: self::TYPE_START,
            id: $id,
            model: $model,
            role: $role,
            created: time(),
        );
    }

    /**
     * 创建内容增量事件
     */
    public static function delta(string $id, string $content): self
    {
        return new self(
            type: self::TYPE_CONTENT_DELTA,
            id: $id,
            contentDelta: $content,
        );
    }

    /**
     * 创建工具调用事件
     */
    public static function toolUse(string $id, StandardToolCall $toolCall): self
    {
        return new self(
            type: self::TYPE_TOOL_USE,
            id: $id,
            toolCall: $toolCall,
        );
    }

    /**
     * 创建结束事件
     */
    public static function finish(
        string $id,
        ?string $finishReason = null,
        ?StandardUsage $usage = null
    ): self {
        return new self(
            type: self::TYPE_FINISH,
            id: $id,
            finishReason: $finishReason,
            usage: $usage,
        );
    }

    /**
     * 创建错误事件
     */
    public static function error(string $id, string $message, ?string $type = null): self
    {
        return new self(
            type: self::TYPE_ERROR,
            id: $id,
            errorMessage: $message,
            errorType: $type,
        );
    }

    /**
     * 是否是开始事件
     */
    public function isStart(): bool
    {
        return $this->type === self::TYPE_START;
    }

    /**
     * 是否是内容增量事件
     */
    public function isDelta(): bool
    {
        return $this->type === self::TYPE_CONTENT_DELTA;
    }

    /**
     * 是否是结束事件
     */
    public function isFinish(): bool
    {
        return $this->type === self::TYPE_FINISH;
    }

    /**
     * 是否是错误事件
     */
    public function isError(): bool
    {
        return $this->type === self::TYPE_ERROR;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'id' => $this->id,
        ];

        if ($this->model !== null) {
            $result['model'] = $this->model;
        }
        if ($this->contentDelta !== null) {
            $result['content_delta'] = $this->contentDelta;
        }
        if ($this->role !== null) {
            $result['role'] = $this->role;
        }
        if ($this->toolCall !== null) {
            $result['tool_call'] = $this->toolCall->toArray();
        }
        if ($this->finishReason !== null) {
            $result['finish_reason'] = $this->finishReason;
        }
        if ($this->usage !== null) {
            $result['usage'] = $this->usage->toArray();
        }
        if ($this->errorMessage !== null) {
            $result['error_message'] = $this->errorMessage;
        }
        if ($this->errorType !== null) {
            $result['error_type'] = $this->errorType;
        }
        if ($this->created > 0) {
            $result['created'] = $this->created;
        }

        return $result;
    }
}
