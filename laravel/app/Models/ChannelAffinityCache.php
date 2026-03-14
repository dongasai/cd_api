<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 渠道亲和力缓存模型
 *
 * 用于存储渠道亲和力缓存数据，替代 Redis 缓存
 */
class ChannelAffinityCache extends Model
{
    use HasFactory;

    /**
     * 关联的表名
     */
    protected $table = 'channel_affinity_cache';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rule_id',
        'key_hash',
        'channel_id',
        'channel_name',
        'key_hint',
        'hit_count',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hit_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * 关联的渠道
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * 获取缓存数据
     *
     * @param  int  $ruleId  规则ID
     * @param  string  $keyHash  Key哈希值
     */
    public static function getCache(int $ruleId, string $keyHash): ?array
    {
        $cache = static::where('rule_id', $ruleId)
            ->where('key_hash', $keyHash)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $cache) {
            return null;
        }

        // 增加命中次数
        $cache->increment('hit_count');

        return [
            'channel_id' => $cache->channel_id,
            'channel_name' => $cache->channel_name,
            'rule_id' => $cache->rule_id,
            'key_hint' => $cache->key_hint,
            'created_at' => $cache->created_at->toDateTimeString(),
            'expires_at' => $cache->expires_at?->toDateTimeString(),
            'hit_count' => $cache->hit_count,
        ];
    }

    /**
     * 设置缓存数据
     *
     * @param  int  $ruleId  规则ID
     * @param  string  $keyHash  Key哈希值
     * @param  array  $data  缓存数据
     * @param  int  $ttlSeconds  过期时间（秒）
     */
    public static function setCache(int $ruleId, string $keyHash, array $data, int $ttlSeconds): bool
    {
        $expiresAt = $ttlSeconds > 0 ? now()->addSeconds($ttlSeconds) : null;

        return static::updateOrCreate(
            [
                'rule_id' => $ruleId,
                'key_hash' => $keyHash,
            ],
            [
                'channel_id' => $data['channel_id'],
                'channel_name' => $data['channel_name'],
                'key_hint' => $data['key_hint'] ?? null,
                'hit_count' => 0,
                'expires_at' => $expiresAt,
            ]
        ) !== null;
    }

    /**
     * 删除指定规则的缓存
     *
     * @param  int  $ruleId  规则ID
     * @return int 删除的记录数
     */
    public static function forgetByRule(int $ruleId): int
    {
        return static::where('rule_id', $ruleId)->delete();
    }

    /**
     * 删除所有缓存
     *
     * @return int 删除的记录数
     */
    public static function forgetAll(): int
    {
        return static::query()->delete();
    }

    /**
     * 清理过期缓存
     *
     * @return int 删除的记录数
     */
    public static function cleanExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    /**
     * 获取统计信息
     */
    public static function getStats(): array
    {
        return [
            'total_entries' => static::where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })->count(),
            'total_hits' => static::sum('hit_count'),
        ];
    }
}
