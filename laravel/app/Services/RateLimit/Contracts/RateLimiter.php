<?php

namespace App\Services\RateLimit\Contracts;

use App\Services\RateLimit\DTO\RateLimitContext;
use App\Services\RateLimit\DTO\RateLimitResult;

/**
 * 限流器接口契约
 *
 * 定义所有限流器必须实现的基本操作
 */
interface RateLimiter
{
    /**
     * 检查是否允许请求
     */
    public function check(RateLimitContext $context): RateLimitResult;

    /**
     * 记录使用量
     */
    public function recordUsage(RateLimitContext $context, array $usage): void;

    /**
     * 获取限流器名称
     */
    public function getName(): string;

    /**
     * 获取当前状态
     */
    public function getStatus(RateLimitContext $context): array;
}
