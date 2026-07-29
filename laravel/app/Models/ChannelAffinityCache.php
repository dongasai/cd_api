<?php

namespace App\Models;

use App\Services\ChannelAffinity\ChannelAffinityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 渠道亲和性缓存模型
 *
 * 用于缓存渠道亲和性规则的匹配结果，替代 Redis 缓存，加速请求路由决策。
 *
 * 数据表结构 (channel_affinity_cache):
 * ┌──────────────────┬─────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 类型        │ 说明                                     │
 * ├──────────────────┼─────────────┼─────────────────────────────────────────┤
 * │ id               │ bigint      │ 主键，自增                                │
 * │ rule_id          │ bigint      │ 规则 ID                                  │
 * │ key_hash         │ varchar(64) │ Key 哈希值（唯一约束的一部分）             │
 * │ channel_id       │ bigint      │ 渠道 ID                                  │
 * │ channel_name     │ varchar     │ 渠道名称（冗余存储，避免关联查询）          │
 * │ key_hint         │ varchar     │ Key 提示（用于调试）                      │
 * │ hit_count        │ int         │ 命中次数                                  │
 * │ expires_at       │ timestamp   │ 过期时间                                  │
 * │ created_at       │ timestamp   │ 创建时间                                  │
 * │ updated_at       │ timestamp   │ 更新时间                                  │
 * └──────────────────┴─────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - UNIQUE idx_unique_affinity: (rule_id, key_hash) - 唯一约束
 * - INDEX idx_expires: (expires_at) - 过期时间索引，用于清理
 *
 * 迁移历史：
 * - 2026_03_09_174134: 初始创建表
 *
 * 核心功能：
 * 1. 缓存匹配结果：存储规则+Key对应的渠道信息
 * 2. TTL 过期机制：支持自动过期时间
 * 3. 命中统计：记录缓存命中次数
 * 4. 批量清理：按规则删除、清理过期缓存
 *
 * 缓存键组成：
 * - rule_id: 亲和性规则ID
 * - key_hash: 根据请求参数生成的哈希值（如 source_ip + api_key）
 *
 * @property int $id 主键
 * @property int $rule_id 规则 ID
 * @property string $key_hash Key 哈希值
 * @property int $channel_id 渠道 ID
 * @property string $channel_name 渠道名称
 * @property string|null $key_hint Key 提示
 * @property int $hit_count 命中次数
 * @property Carbon|null $expires_at 过期时间
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property-read Channel $channel 关联的渠道
 *
 * @see ChannelAffinityRule 亲和性规则模型
 * @see ChannelAffinityService 亲和性服务
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
     * 获取缓存数据（核心方法）
     *
     * 查询逻辑：
     * 1. 根据 rule_id 和 key_hash 查找缓存
     * 2. 检查是否过期（expires_at 为空或未过期）
     * 3. 找到缓存后自动增加 hit_count
     * 4. 返回缓存数据数组
     *
     * @param  int  $ruleId  规则ID
     * @param  string  $keyHash  Key哈希值
     * @return array|null 缓存数据数组，未找到返回 null
     *
     * @see ChannelAffinityService::getAffinity() 使用此方法查询缓存
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
     * 设置缓存数据（核心方法）
     *
     * 使用 updateOrCreate 实现创建或更新缓存
     *
     * @param  int  $ruleId  规则ID
     * @param  string  $keyHash  Key哈希值
     * @param  array  $data  缓存数据（channel_id, channel_name, key_hint）
     * @param  int  $ttlSeconds  过期时间（秒），0表示永不过期
     * @return bool 操作是否成功
     *
     * @see ChannelAffinityService::setAffinity() 使用此方法设置缓存
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
     * 删除指定规则的所有缓存
     *
     * 当规则被修改或删除时，需要清理相关缓存
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
     * 用于全量清理
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
     * 删除 expires_at < now() 的记录
     * 建议通过定时任务定期执行
     *
     * @return int 删除的记录数
     */
    public static function cleanExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    /**
     * 获取缓存统计信息
     *
     * 统计有效缓存条目数和总命中次数
     *
     * @return array{total_entries: int, total_hits: int}
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
