<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * Anthropic 容器信息结构体
 *
 * 用于代码执行工具的容器信息
 *
 * @see https://docs.anthropic.com/en/api/messages#response-body-container
 */
class Container
{
    use JsonSerializiable;

    /**
     * @param  string  $id  容器标识符（必需）
     * @param  string|null  $expires_at  容器过期时间（ISO 8601 格式）
     * @param  array  $additionalData  额外字段（透传）
     */
    public function __construct(
        public string $id = '',
        public ?string $expires_at = null,
        public array $additionalData = [],
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'id' => 'required|string',
            'expires_at' => 'nullable|string|date',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        // 提取已知字段
        $knownKeys = ['id', 'expires_at'];

        $additionalData = array_diff_key($data, array_flip($knownKeys));

        return new self(
            id: $data['id'] ?? '',
            expires_at: $data['expires_at'] ?? null,
            additionalData: $additionalData,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
        ];

        if ($this->expires_at !== null) {
            $result['expires_at'] = $this->expires_at;
        }

        // 合并额外字段（透传）
        return array_merge($result, $this->additionalData);
    }
}
