<?php

namespace App\Services\RateLimit\Limiters;

use App\Services\RateLimit\DTO\RateLimitContext;

/**
 * 模型级别限流器
 *
 * 基于模型名称进行全局滑动窗口限流
 * 用于对特定模型的全局访问频率控制
 */
class ModelLimiter extends RedisSlidingWindowLimiter
{
    /**
     * 创建模型限流器
     *
     * @param  int  $maxRequests  窗口内最大请求数
     * @param  int  $windowSeconds  窗口大小（秒）
     */
    public function __construct(
        int $maxRequests = 500,
        int $windowSeconds = 60,
    ) {
        parent::__construct(
            name: 'model',
            maxRequests: $maxRequests,
            windowSeconds: $windowSeconds,
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function buildKey(RateLimitContext $context): string
    {
        $key = $this->getKeyPrefix().'model:';

        if ($context->model !== null) {
            $key .= str_replace(['/', ':'], '_', $context->model);
        } else {
            $key .= 'default';
        }

        return $key;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDeniedReason(RateLimitContext $context): string
    {
        $model = $context->model ?? 'default';

        return "模型 {$model} 全局请求频率已达上限（{$this->maxRequests} RPM）";
    }

    /**
     * 创建带自定义配置的实例
     */
    public static function make(?int $maxRequests = null, ?int $windowSeconds = null): self
    {
        return new self(
            maxRequests: $maxRequests ?? config('rate_limit.model.max_requests', 500),
            windowSeconds: $windowSeconds ?? config('rate_limit.model.window_seconds', 60),
        );
    }
}
