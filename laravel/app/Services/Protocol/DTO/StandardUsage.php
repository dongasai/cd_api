<?php

namespace App\Services\Protocol\DTO;

/**
 * 标准 Token 使用量 DTO
 */
class StandardUsage
{
    public function __construct(
        // 输入 Token 数量
        public int $promptTokens = 0,

        // 输出 Token 数量
        public int $completionTokens = 0,

        // 总 Token 数量
        public int $totalTokens = 0,

        // 缓存读取 Token (Anthropic 特有)
        public ?int $cacheReadTokens = null,

        // 缓存写入 Token (Anthropic 特有)
        public ?int $cacheWriteTokens = null,

        // 音频 Token
        public ?int $audioTokens = null,

        // 推理 Token (o1 模型)
        public ?int $reasoningTokens = null,
    ) {}

    /**
     * 从 OpenAI 格式创建
     */
    public static function fromOpenAI(array $usage): self
    {
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;

        // 处理详细的 prompt_tokens_details
        $promptDetails = $usage['prompt_tokens_details'] ?? [];
        $completionDetails = $usage['completion_tokens_details'] ?? [];

        return new self(
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $usage['total_tokens'] ?? ($promptTokens + $completionTokens),
            audioTokens: $promptDetails['audio_tokens'] ?? $completionDetails['audio_tokens'] ?? null,
            reasoningTokens: $completionDetails['reasoning_tokens'] ?? null,
        );
    }

    /**
     * 从 Anthropic 格式创建
     */
    public static function fromAnthropic(array $usage): self
    {
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        // 处理缓存相关字段
        $cacheRead = $usage['cache_read_input_tokens'] ?? null;
        $cacheWrite = $usage['cache_creation_input_tokens'] ?? null;

        return new self(
            promptTokens: $inputTokens,
            completionTokens: $outputTokens,
            totalTokens: $inputTokens + $outputTokens,
            cacheReadTokens: $cacheRead,
            cacheWriteTokens: $cacheWrite,
        );
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        $result = [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];

        // 添加详细字段
        $promptDetails = [];
        $completionDetails = [];

        if ($this->audioTokens !== null) {
            $promptDetails['audio_tokens'] = $this->audioTokens;
        }

        if ($this->reasoningTokens !== null) {
            $completionDetails['reasoning_tokens'] = $this->reasoningTokens;
        }

        if (! empty($promptDetails)) {
            $result['prompt_tokens_details'] = $promptDetails;
        }

        if (! empty($completionDetails)) {
            $result['completion_tokens_details'] = $completionDetails;
        }

        return $result;
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): array
    {
        $result = [
            'input_tokens' => $this->promptTokens,
            'output_tokens' => $this->completionTokens,
            // 缓存 Token 始终返回，默认为 0
            'cache_read_input_tokens' => $this->cacheReadTokens ?? 0,
        ];

        if ($this->cacheWriteTokens !== null) {
            $result['cache_creation_input_tokens'] = $this->cacheWriteTokens;
        }

        return $result;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
            'cache_write_tokens' => $this->cacheWriteTokens,
            'audio_tokens' => $this->audioTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }

    /**
     * 合并使用量 (用于流式累加)
     */
    public function merge(StandardUsage $other): self
    {
        return new self(
            promptTokens: $this->promptTokens + $other->promptTokens,
            completionTokens: $this->completionTokens + $other->completionTokens,
            totalTokens: $this->totalTokens + $other->totalTokens,
            cacheReadTokens: ($this->cacheReadTokens ?? 0) + ($other->cacheReadTokens ?? 0),
            cacheWriteTokens: ($this->cacheWriteTokens ?? 0) + ($other->cacheWriteTokens ?? 0),
            audioTokens: ($this->audioTokens ?? 0) + ($other->audioTokens ?? 0),
            reasoningTokens: ($this->reasoningTokens ?? 0) + ($other->reasoningTokens ?? 0),
        );
    }

    /**
     * 计算总 Token
     */
    public function calculateTotal(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
