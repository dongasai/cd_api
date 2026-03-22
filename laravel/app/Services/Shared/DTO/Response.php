<?php

namespace App\Services\Shared\DTO;

use App\Services\Shared\Enums\FinishReason;

/**
 * 统一响应 DTO
 *
 * 纯数据容器，不包含业务逻辑
 */
class Response
{
    public function __construct(
        public string $id,
        public string $model,
        public array $choices, // Choice[]
        public ?Usage $usage = null,
        public ?FinishReason $finishReason = null,
        public ?string $systemFingerprint = null,
        public int $created = 0,
        public ?array $toolCalls = null, // ToolCall[]
        public ?array $container = null, // Container info (Anthropic)
        public ?array $rawResponse = null,
    ) {}

    /**
     * 获取第一个选择的内容
     */
    public function getContent(): ?string
    {
        $choice = $this->choices[0] ?? null;
        if ($choice === null) {
            return null;
        }

        return $choice['message']['content'] ?? null;
    }

    /**
     * 获取第一个选择的工具调用
     */
    public function getToolCalls(): ?array
    {
        $choice = $this->choices[0] ?? null;
        if ($choice === null) {
            return null;
        }

        return $choice['message']['tool_calls'] ?? null;
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        $result = [
            'id' => $this->id,
            'object' => 'chat.completion',
            'created' => $this->created ?: time(),
            'model' => $this->model,
            'choices' => $this->choices,
        ];

        if ($this->usage !== null) {
            $result['usage'] = $this->usage->toOpenAI();
        }

        if ($this->systemFingerprint !== null) {
            $result['system_fingerprint'] = $this->systemFingerprint;
        }

        return $result;
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): array
    {
        $choice = $this->choices[0] ?? [];
        $message = $choice['message'] ?? [];

        $result = [
            'id' => $this->id,
            'type' => 'message',
            'role' => 'assistant',
            'model' => $this->model,
            'content' => $message['content'] ?? [],
        ];

        if ($this->finishReason !== null) {
            $result['stop_reason'] = $this->finishReason->toAnthropic();
        }

        if ($this->usage !== null) {
            $result['usage'] = $this->usage->toAnthropic();
        }

        if ($this->container !== null) {
            $result['container'] = $this->container;
        }

        return $result;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model,
            'choices' => $this->choices,
            'usage' => $this->usage?->toArray(),
            'finish_reason' => $this->finishReason?->value,
            'system_fingerprint' => $this->systemFingerprint,
            'created' => $this->created,
        ];
    }
}
