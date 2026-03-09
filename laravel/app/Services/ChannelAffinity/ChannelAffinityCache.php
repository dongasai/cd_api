<?php

namespace App\Services\ChannelAffinity;

use App\Models\Channel;
use Illuminate\Support\Facades\Cache;

class ChannelAffinityCache
{
    protected string $keyPrefix = 'channel_affinity';

    public function get(int $ruleId, string $keyHash): ?array
    {
        $cacheKey = $this->buildCacheKey($ruleId, $keyHash);

        return Cache::get($cacheKey);
    }

    public function put(int $ruleId, string $keyHash, array $data, int $ttlSeconds): bool
    {
        $cacheKey = $this->buildCacheKey($ruleId, $keyHash);

        return Cache::put($cacheKey, $data, $ttlSeconds);
    }

    public function forget(int $ruleId, string $keyHash): bool
    {
        $cacheKey = $this->buildCacheKey($ruleId, $keyHash);

        return Cache::forget($cacheKey);
    }

    public function forgetByRule(int $ruleId): int
    {
        $pattern = "{$this->keyPrefix}:{$ruleId}:*";
        $count = 0;

        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            foreach ($keys as $key) {
                $redis->del($key);
                $count++;
            }
        }

        return $count;
    }

    public function forgetAll(): int
    {
        $pattern = "{$this->keyPrefix}:*";
        $count = 0;

        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            foreach ($keys as $key) {
                $redis->del($key);
                $count++;
            }
        }

        return $count;
    }

    protected function buildCacheKey(int $ruleId, string $keyHash): string
    {
        return "{$this->keyPrefix}:{$ruleId}:{$keyHash}";
    }

    public function buildCacheData(Channel $channel, int $ruleId, string $keyHint): array
    {
        return [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'rule_id' => $ruleId,
            'key_hint' => $keyHint,
            'created_at' => now()->toDateTimeString(),
            'expires_at' => null,
            'hit_count' => 0,
        ];
    }

    public function getStats(): array
    {
        $stats = [
            'total_entries' => 0,
            'total_hits' => 0,
        ];

        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            $keys = $redis->keys("{$this->keyPrefix}:*");
            $stats['total_entries'] = count($keys);
        }

        return $stats;
    }
}
