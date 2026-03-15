<?php

namespace App\Services\Shared\DTO;

/**
 * 使用量 DTO（合并 TokenUsage + StandardUsage）
 *
 * 纯数据容器，不包含业务逻辑
 */
class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?int $totalTokens = null,
        public ?int $cacheReadInputTokens = null,
        public ?int $cacheCreationInputTokens = null,
        public ?int $audioTokens = null,
        public ?int $reasoningTokens = null,
    ) {
        // 如果没有提供 totalTokens，则自动计算
        $this->totalTokens = $totalTokens ?? ($this->inputTokens + $this->outputTokens);
    }

    /**
     * 从 OpenAI 格式创建
     */
    public static function fromOpenAI(array $usage): self
    {
        return new self(
            inputTokens: $usage['prompt_tokens'] ?? 0,
            outputTokens: $usage['completion_tokens'] ?? 0,
            cacheReadInputTokens: $usage['prompt_tokens_details']['cached_tokens'] ?? null,
            cacheCreationInputTokens: null,
            audioTokens: $usage['prompt_tokens_details']['audio_tokens'] ?? null,
            reasoningTokens: $usage['completion_tokens_details']['reasoning_tokens'] ?? null,
        );
    }

    /**
     * 从 Anthropic 格式创建
     */
    public static function fromAnthropic(array $usage): self
    {
        return new self(
            inputTokens: $usage['input_tokens'] ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
            cacheReadInputTokens: $usage['cache_read_input_tokens'] ?? null,
            cacheCreationInputTokens: $usage['cache_creation_input_tokens'] ?? null,
        );
    }

    /**
     * 获取总 Token 数量
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * 合并两个使用量对象（用于流式累加）
     */
    public function merge(Usage $other): self
    {
        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            cacheReadInputTokens: ($this->cacheReadInputTokens ?? 0) + ($other->cacheReadInputTokens ?? 0),
            cacheCreationInputTokens: ($this->cacheCreationInputTokens ?? 0) + ($other->cacheCreationInputTokens ?? 0),
            audioTokens: ($this->audioTokens ?? 0) + ($other->audioTokens ?? 0),
            reasoningTokens: ($this->reasoningTokens ?? 0) + ($other->reasoningTokens ?? 0),
        );
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        $result = [
            'prompt_tokens' => $this->inputTokens,
            'completion_tokens' => $this->outputTokens,
            'total_tokens' => $this->getTotalTokens(),
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
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens ?? 0,
        ];

        if ($this->cacheCreationInputTokens !== null) {
            $result['cache_creation_input_tokens'] = $this->cacheCreationInputTokens;
        }

        return $result;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
            'cache_creation_input_tokens' => $this->cacheCreationInputTokens,
            'audio_tokens' => $this->audioTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }
}
