<?php

namespace App\Services\RateLimit\Limiters;

use App\Services\RateLimit\DTO\RateLimitContext;
use App\Services\RateLimit\DTO\RateLimitResult;

/**
 * 系统级限流器
 *
 * 对整个系统进行滑动窗口限流，作为最后一道防线
 */
class SystemRateLimiter extends RedisSlidingWindowLimiter
{
    /**
     * 创建系统级限流器
     */
    public function __construct()
    {
        $config = $this->getSystemConfig();

        parent::__construct(
            name: 'system_global',
            maxRequests: $config['maxRequests'],
            windowSeconds: $config['windowSeconds'],
        );
    }

    /**
     * 获取系统限流配置
     *
     * @return array{maxRequests: int, windowSeconds: int}
     */
    protected function getSystemConfig(): array
    {
        return [
            'maxRequests' => (int) config('rate_limit.system.max_requests', 100000),
            'windowSeconds' => (int) config('rate_limit.system.window_size', 60),
        ];
    }

    /**
     * 检查系统限流是否启用
     */
    public static function isEnabled(): bool
    {
        return config('rate_limit.system.enabled', false) === true;
    }

    /**
     * {@inheritDoc}
     */
    protected function buildKey(RateLimitContext $context): string
    {
        $key = $this->buildFullKey('system:global');

        // 可按模型细分
        if ($context->model !== null) {
            $key .= ':'.str_replace(['/', ':'], '_', $context->model);
        }

        return $key;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDeniedReason(RateLimitContext $context): string
    {
        return sprintf(
            '系统繁忙，请稍后重试（系统限流：%d 请求/%d 秒）',
            $this->maxRequests,
            $this->windowSeconds
        );
    }

    /**
     * {@inheritDoc}
     *
     * 系统限流默认不启用，需要时手动启用
     */
    public function check(RateLimitContext $context): RateLimitResult
    {
        if (! self::isEnabled()) {
            return RateLimitResult::allowed();
        }

        return parent::check($context);
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(RateLimitContext $context): array
    {
        if (! self::isEnabled()) {
            return [
                'name' => $this->getName(),
                'enabled' => false,
            ];
        }

        return array_merge(
            parent::getStatus($context),
            ['enabled' => true]
        );
    }
}
