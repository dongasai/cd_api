<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\DTO\Usage as SharedUsage;

/**
 * Anthropic Token 使用量结构体
 *
 * @see https://docs.anthropic.com/en/api/messages#response-usage
 */
class Usage
{
    use Convertible;
    use JsonSerializiable;

    /**
     * @param  int  $input_tokens  输入 token 数
     * @param  int  $output_tokens  输出 token 数
     * @param  array|null  $cache_creation_input_tokens  缓存创建输入 token
     * @param  array|null  $cache_read_input_tokens  缓存读取输入 token
     */
    public function __construct(
        public int $input_tokens = 0,
        public int $output_tokens = 0,
        public ?array $cache_creation_input_tokens = null,
        public ?array $cache_read_input_tokens = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'input_tokens' => 'required|integer|min:0',
            'output_tokens' => 'required|integer|min:0',
            'cache_creation_input_tokens' => 'nullable|array',
            'cache_read_input_tokens' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            input_tokens: $data['input_tokens'] ?? 0,
            output_tokens: $data['output_tokens'] ?? 0,
            cache_creation_input_tokens: $data['cache_creation_input_tokens'] ?? null,
            cache_read_input_tokens: $data['cache_read_input_tokens'] ?? null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
        ];

        if ($this->cache_creation_input_tokens !== null) {
            $result['cache_creation_input_tokens'] = $this->cache_creation_input_tokens;
        }

        if ($this->cache_read_input_tokens !== null) {
            $result['cache_read_input_tokens'] = $this->cache_read_input_tokens;
        }

        return $result;
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedUsage
    {
        $cacheRead = null;
        $cacheCreation = null;

        if ($this->cache_read_input_tokens !== null) {
            $cacheRead = $this->cache_read_input_tokens['input_tokens'] ?? null;
        }

        if ($this->cache_creation_input_tokens !== null) {
            $cacheCreation = $this->cache_creation_input_tokens['input_tokens'] ?? null;
        }

        return new SharedUsage(
            inputTokens: $this->input_tokens,
            outputTokens: $this->output_tokens,
            totalTokens: $this->input_tokens + $this->output_tokens,
            cacheReadInputTokens: $cacheRead,
            cacheCreationInputTokens: $cacheCreation,
        );
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        return new self(
            input_tokens: $dto->inputTokens ?? 0,
            output_tokens: $dto->outputTokens ?? 0,
        );
    }
}
