<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\DTO\Usage as SharedUsage;

/**
 * OpenAI Token 使用量结构体
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object#chat/object-usage
 */
class Usage
{
    use Convertible;
    use JsonSerializiable;

    /**
     * @param  int  $prompt_tokens  输入 token 数
     * @param  int|null  $completion_tokens  输出 token 数（可能为空）
     * @param  int  $total_tokens  总 token 数
     * @param  array|null  $prompt_tokens_details  输入 token 详情
     * @param  array|null  $completion_tokens_details  输出 token 详情
     */
    public function __construct(
        public int $prompt_tokens = 0,
        public ?int $completion_tokens = null,
        public int $total_tokens = 0,
        public ?array $prompt_tokens_details = null,
        public ?array $completion_tokens_details = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'prompt_tokens' => 'required|integer|min:0',
            'completion_tokens' => 'nullable|integer|min:0',
            'total_tokens' => 'required|integer|min:0',
            'prompt_tokens_details' => 'nullable|array',
            'completion_tokens_details' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            prompt_tokens: $data['prompt_tokens'] ?? 0,
            completion_tokens: $data['completion_tokens'] ?? 0,
            total_tokens: $data['total_tokens'] ?? 0,
            prompt_tokens_details: $data['prompt_tokens_details'] ?? null,
            completion_tokens_details: $data['completion_tokens_details'] ?? null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'prompt_tokens' => $this->prompt_tokens,
            'total_tokens' => $this->total_tokens,
        ];

        // completion_tokens 可能为空（预检响应等情况）
        if ($this->completion_tokens !== null) {
            $result['completion_tokens'] = $this->completion_tokens;
        }

        if ($this->prompt_tokens_details !== null) {
            $result['prompt_tokens_details'] = $this->prompt_tokens_details;
        }

        if ($this->completion_tokens_details !== null) {
            $result['completion_tokens_details'] = $this->completion_tokens_details;
        }

        return $result;
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedUsage
    {
        return new SharedUsage(
            inputTokens: $this->prompt_tokens,
            outputTokens: $this->completion_tokens ?? 0,
            totalTokens: $this->total_tokens,
            cacheReadInputTokens: $this->prompt_tokens_details['cached_tokens'] ?? null,
            cacheCreationInputTokens: null,
        );
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        return new self(
            prompt_tokens: $dto->inputTokens ?? 0,
            completion_tokens: $dto->outputTokens ?? 0,
            total_tokens: $dto->totalTokens ?? 0,
        );
    }
}
