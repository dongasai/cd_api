<?php

namespace App\Services\RateLimit;

use App\Models\ApiKey;
use App\Models\Channel;
use App\Services\RateLimit\Contracts\RateLimiter;
use App\Services\RateLimit\DTO\RateLimitContext;
use App\Services\RateLimit\DTO\RateLimitResult;
use App\Services\RateLimit\Exceptions\RateLimitExceededException;
use App\Services\RateLimit\Limiters\ApiKeyRateLimiter;
use App\Services\RateLimit\Limiters\ChannelRateLimiter;
use App\Services\RateLimit\Limiters\SystemRateLimiter;
use Illuminate\Support\Collection;

/**
 * 限流管理器
 *
 * 协调所有限流器，按优先级进行检查和记录
 * 优先级：API Key (RPM/RPD) -> 渠道 -> 系统
 */
class RateLimitManager
{
    /**
     * 注册的限流器集合
     *
     * @var Collection<int, RateLimiter>
     */
    protected Collection $limiters;

    /**
     * 是否已注册 API Key 限流器
     */
    protected bool $hasApiKeyLimiters = false;

    /**
     * 是否已注册渠道限流器
     */
    protected bool $hasChannelLimiters = false;

    /**
     * 是否已注册系统限流器
     */
    protected bool $hasSystemLimiter = false;

    public function __construct()
    {
        $this->limiters = new Collection;
    }

    /**
     * 检查全局限流是否启用
     */
    public static function isEnabled(): bool
    {
        return config('rate_limit.enabled', true) === true;
    }

    /**
     * 注册 API Key 限流器
     *
     * 为 API Key 注册 RPM、RPD、TPD 三种限流器
     */
    public function registerApiKeyLimiters(ApiKey $apiKey): self
    {
        if (! self::isEnabled()) {
            return $this;
        }

        if (! config('rate_limit.api_key.enabled', true)) {
            return $this;
        }

        // 防止重复注册
        if ($this->hasApiKeyLimiters) {
            return $this;
        }

        // 注册 RPM 限流器
        $this->limiters->push(new ApiKeyRateLimiter($apiKey, 'rpm'));

        // 注册 RPD 限流器
        $this->limiters->push(new ApiKeyRateLimiter($apiKey, 'rpd'));

        // 注册 TPD 限流器（Token 每天限制）
        $this->limiters->push(new ApiKeyRateLimiter($apiKey, 'tpd'));

        $this->hasApiKeyLimiters = true;

        return $this;
    }

    /**
     * 注册渠道限流器
     *
     * 为渠道注册整体级别和模型级别的限流器
     */
    public function registerChannelLimiters(Channel $channel, ?string $model = null): self
    {
        if (! self::isEnabled()) {
            return $this;
        }

        if (! config('rate_limit.channel.enabled', true)) {
            return $this;
        }

        // 防止重复注册（同一渠道+模型组合）
        $key = "{$channel->id}:".($model ?? 'global');
        foreach ($this->limiters as $limiter) {
            if ($limiter instanceof ChannelRateLimiter) {
                $limiterKey = "{$limiter->getChannel()->id}:".($limiter->getModel() ?? 'global');
                if ($limiterKey === $key) {
                    return $this;
                }
            }
        }

        // 注册渠道限流器
        $this->limiters->push(new ChannelRateLimiter($channel, $model));

        return $this;
    }

    /**
     * 注册系统限流器
     */
    public function registerSystemLimiter(): self
    {
        if (! self::isEnabled()) {
            return $this;
        }

        if (! SystemRateLimiter::isEnabled()) {
            return $this;
        }

        // 防止重复注册
        if ($this->hasSystemLimiter) {
            return $this;
        }

        $this->limiters->push(new SystemRateLimiter);
        $this->hasSystemLimiter = true;

        return $this;
    }

    /**
     * 检查限流
     *
     * 按优先级检查（Key → 渠道 → 系统），快速失败
     * 被拒绝时抛出 RateLimitExceededException
     *
     * @throws RateLimitExceededException
     */
    public function check(RateLimitContext $context): RateLimitResult
    {
        if (! self::isEnabled()) {
            return RateLimitResult::allowed();
        }

        $allLimits = [];
        $allUsage = [];

        foreach ($this->limiters as $limiter) {
            $result = $limiter->check($context);

            $allLimits[$limiter->getName()] = $result->limits;
            $allUsage[$limiter->getName()] = $result->usage;

            if ($result->isDenied()) {
                throw new RateLimitExceededException(
                    message: $result->reason ?? 'Rate limit exceeded',
                    retryAfter: $result->retryAfter,
                );
            }
        }

        return RateLimitResult::allowed(limits: $allLimits, usage: $allUsage);
    }

    /**
     * 记录使用量
     *
     * 通知所有已注册的限流器记录使用量
     */
    public function recordUsage(RateLimitContext $context, array $usage): void
    {
        if (! self::isEnabled()) {
            return;
        }

        foreach ($this->limiters as $limiter) {
            $limiter->recordUsage($context, $usage);
        }
    }

    /**
     * 获取所有限流器状态
     *
     * @return array<int, array>
     */
    public function getAllStatus(RateLimitContext $context): array
    {
        if (! self::isEnabled()) {
            return [
                'enabled' => false,
                'limiters' => [],
            ];
        }

        $statuses = [];

        foreach ($this->limiters as $limiter) {
            $statuses[] = $limiter->getStatus($context);
        }

        return [
            'enabled' => true,
            'limiters' => $statuses,
        ];
    }

    /**
     * 重置所有限流器计数
     */
    public function resetAll(RateLimitContext $context): void
    {
        foreach ($this->limiters as $limiter) {
            if (method_exists($limiter, 'reset')) {
                $limiter->reset($context);
            }
        }
    }

    /**
     * 清除所有注册的限流器
     */
    public function clear(): self
    {
        $this->limiters = new Collection;
        $this->hasApiKeyLimiters = false;
        $this->hasChannelLimiters = false;
        $this->hasSystemLimiter = false;

        return $this;
    }

    /**
     * 获取已注册的限流器数量
     */
    public function getLimiterCount(): int
    {
        return $this->limiters->count();
    }

    /**
     * 获取已注册的限流器列表
     *
     * @return Collection<int, RateLimiter>
     */
    public function getLimiters(): Collection
    {
        return $this->limiters;
    }
}
