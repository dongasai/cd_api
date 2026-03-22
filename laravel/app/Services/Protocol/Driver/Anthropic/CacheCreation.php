<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * Anthropic 缓存创建信息结构体
 *
 * @see https://docs.anthropic.com/en/api/messages#response-usage
 */
class CacheCreation
{
    use Convertible;
    use JsonSerializiable;

    /**
     * @param  int  $ephemeral_1h_input_tokens  1小时缓存创建的输入 token 数
     * @param  int  $ephemeral_5m_input_tokens  5分钟缓存创建的输入 token 数
     */
    public function __construct(
        public int $ephemeral_1h_input_tokens = 0,
        public int $ephemeral_5m_input_tokens = 0,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'ephemeral_1h_input_tokens' => 'integer|min:0',
            'ephemeral_5m_input_tokens' => 'integer|min:0',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            ephemeral_1h_input_tokens: $data['ephemeral_1h_input_tokens'] ?? 0,
            ephemeral_5m_input_tokens: $data['ephemeral_5m_input_tokens'] ?? 0,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'ephemeral_1h_input_tokens' => $this->ephemeral_1h_input_tokens,
            'ephemeral_5m_input_tokens' => $this->ephemeral_5m_input_tokens,
        ];
    }
}
