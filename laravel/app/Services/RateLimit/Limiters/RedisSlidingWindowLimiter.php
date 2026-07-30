<?php

namespace App\Services\RateLimit\Limiters;

use App\Services\RateLimit\Contracts\RateLimiter;
use App\Services\RateLimit\DTO\RateLimitContext;
use App\Services\RateLimit\DTO\RateLimitResult;
use Illuminate\Support\Facades\Redis;

/**
 * Redis 滑动窗口限流器基类
 *
 * 使用 Redis Sorted Set (ZSET) 实现精确的滑动窗口限流
 * Lua 脚本保证操作的原子性
 */
abstract class RedisSlidingWindowLimiter implements RateLimiter
{
    /**
     * Lua 脚本：滑动窗口检查与记录
     *
     * KEYS[1]: 窗口键名
     * ARGV[1]: 当前时间戳（毫秒）
     * ARGV[2]: 窗口起始时间戳（毫秒）
     * ARGV[3]: 最大请求数
     * ARGV[4]: 窗口大小（秒）
     *
     * 返回: [是否允许, 当前请求数, 剩余配额, 重试等待时间]
     */
    protected const SLIDING_WINDOW_SCRIPT = <<<'LUA'
local key = KEYS[1]
local now = tonumber(ARGV[1])
local window_start = tonumber(ARGV[2])
local max_requests = tonumber(ARGV[3])
local window_size = tonumber(ARGV[4])

-- 删除过期数据
redis.call('ZREMRANGEBYSCORE', key, '-inf', window_start)

-- 统计当前窗口内的请求数
local current = redis.call('ZCARD', key)

if current < max_requests then
    -- 添加新请求，使用 当前时间戳-随机数 作为唯一成员
    redis.call('ZADD', key, now, now .. '-' .. math.random())
    redis.call('EXPIRE', key, window_size)
    return {1, current + 1, max_requests - current - 1, 0}
else
    -- 计算重试时间：最老请求的时间 + 窗口大小 - 当前时间
    local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
    local retry_after = math.ceil(tonumber(oldest[2]) / 1000 + window_size - now / 1000)
    return {0, current, 0, retry_after}
end
LUA;

    /**
     * @param  string  $name  限流器名称
     * @param  int  $maxRequests  窗口内最大请求数
     * @param  int  $windowSeconds  窗口大小（秒）
     */
    public function __construct(
        protected string $name,
        protected int $maxRequests,
        protected int $windowSeconds,
    ) {}

    /**
     * 构建 Redis Key
     *
     * 由子类实现具体的键名构建逻辑
     */
    abstract protected function buildKey(RateLimitContext $context): string;

    /**
     * 获取拒绝原因消息
     *
     * 由子类实现具体的拒绝原因
     */
    abstract protected function getDeniedReason(RateLimitContext $context): string;

    /**
     * {@inheritDoc}
     */
    public function check(RateLimitContext $context): RateLimitResult
    {
        $key = $this->buildKey($context);
        $now = (int) (microtime(true) * 1000); // 毫秒时间戳
        $windowStart = $now - ($this->windowSeconds * 1000);
        $connection = $this->getRedisConnection();

        // 执行 Lua 脚本
        $result = Redis::connection($connection)->eval(
            self::SLIDING_WINDOW_SCRIPT,
            1, // key 数量
            $key,
            $now,
            $windowStart,
            $this->maxRequests,
            $this->windowSeconds
        );

        $allowed = (bool) $result[0];
        $current = (int) $result[1];
        $remaining = (int) $result[2];
        $retryAfter = (int) $result[3];

        if ($allowed) {
            return RateLimitResult::allowed(
                limits: ['max' => $this->maxRequests, 'window' => $this->windowSeconds],
                usage: ['current' => $current, 'remaining' => $remaining],
            );
        }

        return RateLimitResult::denied(
            reason: $this->getDeniedReason($context),
            retryAfter: $retryAfter > 0 ? $retryAfter : null,
            limits: ['max' => $this->maxRequests, 'window' => $this->windowSeconds],
            usage: ['current' => $current, 'remaining' => 0],
        );
    }

    /**
     * {@inheritDoc}
     *
     * 滑动窗口已在 check() 中记录，此方法用于额外记录用量详情
     */
    public function recordUsage(RateLimitContext $context, array $usage): void
    {
        // 滑动窗口在 check 时已经记录了请求
        // 子类可重写此方法记录额外的使用量信息（如 Token 数）
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     *
     * 返回当前窗口状态：使用量、剩余配额、窗口信息
     */
    public function getStatus(RateLimitContext $context): array
    {
        $key = $this->buildKey($context);
        $now = (int) (microtime(true) * 1000);
        $windowStart = $now - ($this->windowSeconds * 1000);
        $connection = $this->getRedisConnection();

        $redis = Redis::connection($connection);

        // 删除过期数据
        $redis->zremrangebyscore($key, '-inf', $windowStart);

        // 获取当前请求数
        $current = $redis->zcard($key);

        // 获取窗口内最早的请求时间
        $oldest = $redis->zrange($key, 0, 0, 'WITHSCORES');
        $oldestTimestamp = $oldest[0][1] ?? null;

        // 计算剩余时间
        $remainingTime = null;
        if ($oldestTimestamp !== null) {
            $remainingTime = max(0, (int) (($oldestTimestamp / 1000 + $this->windowSeconds) - $now / 1000));
        }

        return [
            'key' => $key,
            'window_seconds' => $this->windowSeconds,
            'max_requests' => $this->maxRequests,
            'current_requests' => $current,
            'remaining_requests' => max(0, $this->maxRequests - $current),
            'remaining_time_seconds' => $remainingTime,
            'oldest_request_at' => $oldestTimestamp ? date('Y-m-d H:i:s', (int) ($oldestTimestamp / 1000)) : null,
            'reset_at' => $remainingTime ? date('Y-m-d H:i:s', time() + $remainingTime) : null,
        ];
    }

    /**
     * 获取 Redis 连接名称
     */
    protected function getRedisConnection(): string
    {
        return config('rate_limit.redis.connection', 'default');
    }

    /**
     * 获取 Redis Key 前缀
     */
    protected function getKeyPrefix(): string
    {
        return config('rate_limit.redis.key_prefix', 'rl:');
    }

    /**
     * 构建完整的 Redis Key
     */
    protected function buildFullKey(string $suffix): string
    {
        return $this->getKeyPrefix().$suffix;
    }

    /**
     * 重置指定上下文的限流计数
     * 主要用于测试或管理操作
     */
    public function reset(RateLimitContext $context): bool
    {
        $key = $this->buildKey($context);
        $connection = $this->getRedisConnection();

        return (bool) Redis::connection($connection)->del($key);
    }
}
