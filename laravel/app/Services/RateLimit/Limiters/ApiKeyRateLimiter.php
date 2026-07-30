<?php

namespace App\Services\RateLimit\Limiters;

use App\Models\ApiKey;
use App\Services\RateLimit\DTO\RateLimitContext;
use App\Services\RateLimit\DTO\RateLimitResult;
use Illuminate\Support\Facades\Redis;

/**
 * API Key 限流器
 *
 * 基于 API Key 的 rate_limit 配置进行滑动窗口限流
 * 支持 RPM、RPD、TPD 三种限流类型
 */
class ApiKeyRateLimiter extends RedisSlidingWindowLimiter
{
    protected ApiKey $apiKey;

    protected string $limitType;

    /**
     * 窗口配置映射
     */
    protected const LIMIT_TYPE_CONFIG = [
        'rpm' => ['configKey' => 'requests_per_minute', 'windowSeconds' => 60],
        'rpd' => ['configKey' => 'requests_per_day', 'windowSeconds' => 86400],
        'tpd' => ['configKey' => 'tokens_per_day', 'windowSeconds' => 86400],
    ];

    /**
     * 创建 API Key 限流器
     */
    public function __construct(ApiKey $apiKey, string $limitType)
    {
        if (! isset(self::LIMIT_TYPE_CONFIG[$limitType])) {
            throw new \InvalidArgumentException("无效的限流类型: {$limitType}");
        }

        $this->apiKey = $apiKey;
        $this->limitType = $limitType;

        $config = $this->getLimitConfig();
        $maxRequests = $config['maxRequests'] ?? 0;
        $windowSeconds = $config['windowSeconds'] ?? 60;

        parent::__construct(
            name: "api_key_{$limitType}",
            maxRequests: $maxRequests,
            windowSeconds: $windowSeconds,
        );
    }

    /**
     * 获取限流配置
     */
    protected function getLimitConfig(): ?array
    {
        $rateLimit = $this->apiKey->rate_limit ?? [];
        $typeConfig = self::LIMIT_TYPE_CONFIG[$this->limitType];
        $configKey = $typeConfig['configKey'];
        $windowSeconds = $typeConfig['windowSeconds'];

        $maxRequests = $rateLimit[$configKey] ?? null;

        // 未配置则不启用此限流维度
        if ($maxRequests === null || (int) $maxRequests <= 0) {
            $maxRequests = 0;
        }

        if ($maxRequests <= 0) {
            return null;
        }

        return [
            'maxRequests' => (int) $maxRequests,
            'windowSeconds' => $windowSeconds,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function check(RateLimitContext $context): RateLimitResult
    {
        // 未配置限流则直接放行
        if ($this->maxRequests <= 0) {
            return RateLimitResult::allowed();
        }

        if ($this->limitType !== 'tpd') {
            return parent::check($context);
        }

        return $this->checkTokenLimit($context);
    }

    /**
     * 检查 Token 限流
     */
    protected function checkTokenLimit(RateLimitContext $context): RateLimitResult
    {
        $config = $this->getLimitConfig();
        if ($config === null) {
            return RateLimitResult::allowed();
        }

        $key = $this->buildKey($context);
        $maxTokens = $config['maxRequests'];
        $windowSeconds = $config['windowSeconds'];

        $redis = Redis::connection($this->getRedisConnection());
        $now = (int) (microtime(true) * 1000);
        $windowStart = $now - ($windowSeconds * 1000);

        $redis->zremrangebyscore($key, '-inf', $windowStart);
        $usedTokens = $this->calculateTotalTokens($redis, $key);
        $estimatedTokens = $context->metadata['estimated_tokens'] ?? 0;

        if ($usedTokens + $estimatedTokens > $maxTokens) {
            return RateLimitResult::denied(
                reason: $this->getDeniedReason($context),
                retryAfter: $this->calculateRetryAfter($redis, $key, $windowSeconds),
                limits: ['max' => $maxTokens, 'window' => $windowSeconds],
                usage: ['current' => $usedTokens, 'estimated' => $estimatedTokens],
            );
        }

        return RateLimitResult::allowed(
            limits: ['max' => $maxTokens, 'window' => $windowSeconds],
            usage: ['current' => $usedTokens, 'available' => $maxTokens - $usedTokens],
        );
    }

    /**
     * 计算 Token 总量
     */
    protected function calculateTotalTokens($redis, string $key): int
    {
        $members = $redis->zrange($key, 0, -1);
        $total = 0;
        foreach ($members as $member) {
            $parts = explode('-', $member, 2);
            if (isset($parts[1]) && is_numeric($parts[1])) {
                $total += (int) $parts[1];
            }
        }

        return $total;
    }

    /**
     * 计算重试等待时间
     */
    protected function calculateRetryAfter($redis, string $key, int $windowSeconds): ?int
    {
        $oldest = $redis->zrange($key, 0, 0, 'WITHSCORES');
        if (empty($oldest)) {
            return null;
        }

        $oldestTimestamp = (int) array_values($oldest)[0] / 1000;

        return max(0, (int) ($oldestTimestamp + $windowSeconds - time()));
    }

    /**
     * {@inheritDoc}
     */
    public function recordUsage(RateLimitContext $context, array $usage): void
    {
        if ($this->limitType !== 'tpd') {
            return;
        }

        $config = $this->getLimitConfig();
        if ($config === null) {
            return;
        }

        $key = $this->buildKey($context);
        $windowSeconds = $config['windowSeconds'];

        $redis = Redis::connection($this->getRedisConnection());
        $now = (int) (microtime(true) * 1000);
        $tokens = $usage['tokens'] ?? 0;

        if ($tokens > 0) {
            $member = $now.'-'.$tokens;
            $redis->zadd($key, $now, $member);
            $redis->expire($key, $windowSeconds + 1);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function buildKey(RateLimitContext $context): string
    {
        return $this->buildFullKey("api_key:{$this->apiKey->id}:{$this->limitType}");
    }

    /**
     * {@inheritDoc}
     */
    protected function getDeniedReason(RateLimitContext $context): string
    {
        $typeNames = [
            'rpm' => '每分钟请求数',
            'rpd' => '每日请求数',
            'tpd' => '每日 Token 数',
        ];

        $typeName = $typeNames[$this->limitType] ?? $this->limitType;

        return sprintf(
            'API Key "%s" %s超出限制（最大 %d）',
            $this->apiKey->name,
            $typeName,
            $this->maxRequests
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getStatus(RateLimitContext $context): array
    {
        if ($this->limitType !== 'tpd') {
            return parent::getStatus($context);
        }

        $config = $this->getLimitConfig();
        if ($config === null) {
            return [
                'name' => $this->getName(),
                'enabled' => false,
            ];
        }

        $key = $this->buildKey($context);
        $windowSeconds = $config['windowSeconds'];

        $redis = Redis::connection($this->getRedisConnection());
        $redis->zremrangebyscore($key, '-inf', (int) (microtime(true) * 1000) - ($windowSeconds * 1000));
        $usedTokens = $this->calculateTotalTokens($redis, $key);

        return [
            'name' => $this->getName(),
            'enabled' => true,
            'type' => 'token',
            'limit' => $config['maxRequests'],
            'window' => $windowSeconds,
            'used_tokens' => $usedTokens,
            'remaining' => max(0, $config['maxRequests'] - $usedTokens),
        ];
    }
}
