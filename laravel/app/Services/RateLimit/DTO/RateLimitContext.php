<?php

namespace App\Services\RateLimit\DTO;

/**
 * 限流上下文数据传输对象
 *
 * 封装限流决策所需的全部上下文信息
 */
readonly class RateLimitContext
{
    /**
     * @param  int|null  $apiKeyId  API Key ID
     * @param  int|null  $channelId  渠道 ID
     * @param  string|null  $model  模型名称
     * @param  array  $metadata  额外元数据
     */
    public function __construct(
        public ?int $apiKeyId = null,
        public ?int $channelId = null,
        public ?string $model = null,
        public array $metadata = [],
    ) {}

    /**
     * 为 API Key 创建上下文
     */
    public static function forApiKey(int $apiKeyId, ?string $model = null, array $metadata = []): self
    {
        return new self(
            apiKeyId: $apiKeyId,
            model: $model,
            metadata: $metadata,
        );
    }

    /**
     * 为渠道创建上下文
     */
    public static function forChannel(int $channelId, ?string $model = null, array $metadata = []): self
    {
        return new self(
            channelId: $channelId,
            model: $model,
            metadata: $metadata,
        );
    }

    /**
     * 为系统级别创建上下文
     */
    public static function forSystem(?string $model = null, array $metadata = []): self
    {
        return new self(
            model: $model,
            metadata: $metadata,
        );
    }

    /**
     * 创建副本并合并元数据
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            apiKeyId: $this->apiKeyId,
            channelId: $this->channelId,
            model: $this->model,
            metadata: array_merge($this->metadata, $metadata),
        );
    }
}
