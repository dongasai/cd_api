<?php

namespace App\Services\RateLimit\DTO;

/**
 * 限流结果数据传输对象
 *
 * 封装限流检查的结果信息
 */
readonly class RateLimitResult
{
    /**
     * @param  bool  $allowed  是否允许请求
     * @param  string|null  $reason  拒绝原因（如果被拒绝）
     * @param  int|null  $retryAfter  重试等待时间（秒）
     * @param  array  $limits  各限流维度的限制信息
     * @param  array  $usage  当前使用量
     */
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?int $retryAfter = null,
        public array $limits = [],
        public array $usage = [],
    ) {}

    /**
     * 创建允许访问的结果
     */
    public static function allowed(array $limits = [], array $usage = []): self
    {
        return new self(
            allowed: true,
            limits: $limits,
            usage: $usage,
        );
    }

    /**
     * 创建拒绝访问的结果
     */
    public static function denied(string $reason, ?int $retryAfter = null, array $limits = [], array $usage = []): self
    {
        return new self(
            allowed: false,
            reason: $reason,
            retryAfter: $retryAfter,
            limits: $limits,
            usage: $usage,
        );
    }

    /**
     * 是否被拒绝
     */
    public function isDenied(): bool
    {
        return ! $this->allowed;
    }

    /**
     * 获取剩余配额
     */
    public function getRemaining(): ?int
    {
        if (isset($this->limits['max'], $this->usage['current'])) {
            return max(0, $this->limits['max'] - $this->usage['current']);
        }

        return null;
    }
}
