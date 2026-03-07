<?php

namespace App\Services\Provider\DTO;

/**
 * Token 使用量数据传输对象
 *
 * 用于封装 AI 请求的 Token 消耗统计
 */
class TokenUsage
{
    /**
     * 输入 Token 数量（提示词）
     */
    public int $promptTokens = 0;

    /**
     * 输出 Token 数量（补全词）
     */
    public int $completionTokens = 0;

    /**
     * 总 Token 数量
     */
    public int $totalTokens = 0;

    /**
     * 构造函数
     *
     * @param  int  $promptTokens  输入 Token 数量
     * @param  int  $completionTokens  输出 Token 数量
     * @param  int  $totalTokens  总 Token 数量
     */
    public function __construct(
        int $promptTokens = 0,
        int $completionTokens = 0,
        int $totalTokens = 0,
    ) {
        $this->promptTokens = $promptTokens;
        $this->completionTokens = $completionTokens;
        // 如果未提供总数，自动计算
        $this->totalTokens = $totalTokens ?: ($promptTokens + $completionTokens);
    }

    /**
     * 从 OpenAI 格式创建实例
     *
     * @param  array  $usage  OpenAI usage 数据
     */
    public static function fromOpenAI(array $usage): self
    {
        return new self(
            promptTokens: $usage['prompt_tokens'] ?? 0,
            completionTokens: $usage['completion_tokens'] ?? 0,
            totalTokens: $usage['total_tokens'] ?? 0,
        );
    }

    /**
     * 从 Anthropic 格式创建实例
     *
     * @param  array  $usage  Anthropic usage 数据
     */
    public static function fromAnthropic(array $usage): self
    {
        return new self(
            promptTokens: $usage['input_tokens'] ?? 0,
            completionTokens: $usage['output_tokens'] ?? 0,
            totalTokens: ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
        );
    }

    /**
     * 转换为 OpenAI 格式
     */
    public function toOpenAI(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    /**
     * 转换为 Anthropic 格式
     */
    public function toAnthropic(): array
    {
        return [
            'input_tokens' => $this->promptTokens,
            'output_tokens' => $this->completionTokens,
        ];
    }

    /**
     * 转换为数组格式
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    /**
     * 合并两个使用量对象
     *
     * 用于流式响应中累加 Token 使用量
     */
    public function add(self $other): self
    {
        return new self(
            promptTokens: $this->promptTokens + $other->promptTokens,
            completionTokens: $this->completionTokens + $other->completionTokens,
            totalTokens: $this->totalTokens + $other->totalTokens,
        );
    }
}
