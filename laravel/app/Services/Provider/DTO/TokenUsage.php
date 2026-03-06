<?php

namespace App\Services\Provider\DTO;

class TokenUsage
{
    public function __construct(
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $totalTokens = 0,
    ) {
        if ($this->totalTokens === 0) {
            $this->totalTokens = $this->promptTokens + $this->completionTokens;
        }
    }

    public static function fromOpenAI(array $usage): self
    {
        return new self(
            promptTokens: $usage['prompt_tokens'] ?? 0,
            completionTokens: $usage['completion_tokens'] ?? 0,
            totalTokens: $usage['total_tokens'] ?? 0,
        );
    }

    public static function fromAnthropic(array $usage): self
    {
        return new self(
            promptTokens: $usage['input_tokens'] ?? 0,
            completionTokens: $usage['output_tokens'] ?? 0,
            totalTokens: ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
        );
    }

    public function toOpenAI(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    public function toAnthropic(): array
    {
        return [
            'input_tokens' => $this->promptTokens,
            'output_tokens' => $this->completionTokens,
        ];
    }

    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    public function add(self $other): self
    {
        return new self(
            promptTokens: $this->promptTokens + $other->promptTokens,
            completionTokens: $this->completionTokens + $other->completionTokens,
            totalTokens: $this->totalTokens + $other->totalTokens,
        );
    }
}
