<?php

namespace App\Services\ChannelAffinity;

use App\Models\Channel;
use App\Models\ChannelAffinityCache as ChannelAffinityCacheModel;

/**
 * 渠道亲和力缓存服务
 *
 * 使用数据库存储替代 Redis 缓存
 */
class ChannelAffinityCache
{
    protected string $keyPrefix = 'channel_affinity';

    /**
     * 获取缓存数据
     *
     * @param  int  $ruleId  规则ID
     * @param  string  $keyHash  Key哈希值
     */
    public function get(int $ruleId, string $keyHash): ?array
    {
        return ChannelAffinityCacheModel::getCache($ruleId, $keyHash);
    }

    /**
     * 设置缓存数据
     *
     * @param  int  $ruleId  规则ID
     * @param  string  $keyHash  Key哈希值
     * @param  array  $data  缓存数据
     * @param  int  $ttlSeconds  过期时间（秒）
     */
    public function put(int $ruleId, string $keyHash, array $data, int $ttlSeconds): bool
    {
        return ChannelAffinityCacheModel::setCache($ruleId, $keyHash, $data, $ttlSeconds);
    }

    /**
     * 删除指定规则的缓存
     *
     * @param  int  $ruleId  规则ID
     * @param  string  $keyHash  Key哈希值
     */
    public function forget(int $ruleId, string $keyHash): bool
    {
        return ChannelAffinityCacheModel::where('rule_id', $ruleId)
            ->where('key_hash', $keyHash)
            ->delete() > 0;
    }

    /**
     * 删除指定规则的所有缓存
     *
     * @param  int  $ruleId  规则ID
     * @return int 删除的记录数
     */
    public function forgetByRule(int $ruleId): int
    {
        return ChannelAffinityCacheModel::forgetByRule($ruleId);
    }

    /**
     * 删除所有缓存
     *
     * @return int 删除的记录数
     */
    public function forgetAll(): int
    {
        return ChannelAffinityCacheModel::forgetAll();
    }

    /**
     * 构建缓存键
     *
     * @param  int  $ruleId  规则ID
     * @param  string  $keyHash  Key哈希值
     */
    protected function buildCacheKey(int $ruleId, string $keyHash): string
    {
        return "{$this->keyPrefix}:{$ruleId}:{$keyHash}";
    }

    /**
     * 构建缓存数据
     *
     * @param  Channel  $channel  渠道
     * @param  int  $ruleId  规则ID
     * @param  string  $keyHint  Key提示
     */
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

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return ChannelAffinityCacheModel::getStats();
    }

    /**
     * 清理过期缓存
     *
     * @return int 删除的记录数
     */
    public function cleanExpired(): int
    {
        return ChannelAffinityCacheModel::cleanExpired();
    }
}
