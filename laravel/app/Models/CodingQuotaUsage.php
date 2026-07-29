<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 配额使用量模型
 *
 * 存储各账户在不同周期的配额使用量，替代 Redis 存储。
 * 通过 (account_id, metric, period_key) 唯一组合标识一条记录。
 *
 * 数据表结构 (coding_quota_usage):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ account_id               │ bigint unsigned       │ Coding 账户 ID                            │
 * │ metric                   │ varchar(50)           │ 指标名称：prompts/tokens/requests 等      │
 * │ period_key               │ varchar(50)           │ 周期标识：Y-m-d、Y-W、Y-m 等              │
 * │ period_type              │ varchar(20)           │ 周期类型：5h/daily/weekly/monthly         │
 * │ used                     │ bigint unsigned       │ 已使用量（默认0）                          │
 * │ period_starts_at         │ timestamp             │ 周期开始时间                              │
 * │ period_ends_at           │ timestamp             │ 周期结束时间                              │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * │ updated_at               │ timestamp             │ 更新时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - UNIQUE (account_id, metric, period_key) - 每账户每指标每周期唯一
 * - INDEX (account_id, period_type) - 按账户+周期类型查询
 * - INDEX (period_ends_at) - 按过期时间查询（清理用）
 *
 * 迁移历史：
 * - 2026_03_13_100001: 初始创建表（替代 Redis 存储）
 *
 * 核心功能：
 * 1. 多指标追踪：支持 prompts/tokens/requests 等多种指标
 * 2. 多周期支持：5h/daily/weekly/monthly 四种周期
 * 3. 原子操作：incrementUsage() 保证并发安全
 * 4. 自动清理：cleanExpired() 清理过期记录
 *
 * @property int $id 主键
 * @property int $account_id Coding 账户 ID
 * @property string $metric 指标名称
 * @property string $period_key 周期标识
 * @property string $period_type 周期类型
 * @property int $used 已使用量
 * @property Carbon|null $period_starts_at 周期开始时间
 * @property Carbon|null $period_ends_at 周期结束时间
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property-read CodingAccount $account 关联的 Coding 账户
 *
 * @see CodingAccount Coding 账户模型
 */
class CodingQuotaUsage extends Model
{
    use HasFactory;

    /**
     * 指定表名（迁移创建的是单数形式）
     */
    protected $table = 'coding_quota_usage';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'metric',
        'period_key',
        'period_type',
        'used',
        'period_starts_at',
        'period_ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used' => 'integer',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }

    /**
     * 关联的 Coding 账户
     *
     * @see CodingAccount
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class, 'account_id');
    }

    /**
     * 获取或创建指定账户、指标、周期的使用记录
     *
     * @param  int  $accountId  账户ID
     * @param  string  $metric  指标名称
     * @param  string  $periodKey  周期标识
     * @param  string  $periodType  周期类型
     * @param  array  $periodInfo  周期信息（starts_at, ends_at）
     */
    public static function getOrCreateForPeriod(
        int $accountId,
        string $metric,
        string $periodKey,
        string $periodType,
        array $periodInfo = []
    ): static {
        return static::firstOrCreate(
            [
                'account_id' => $accountId,
                'metric' => $metric,
                'period_key' => $periodKey,
            ],
            [
                'period_type' => $periodType,
                'used' => 0,
                'period_starts_at' => $periodInfo['starts_at'] ?? null,
                'period_ends_at' => $periodInfo['ends_at'] ?? null,
            ]
        );
    }

    /**
     * 增加使用量
     *
     * @param  int  $amount  增加的数量
     * @return int 增加后的总使用量
     */
    public function incrementUsage(int $amount): int
    {
        $this->increment('used', $amount);

        return $this->used;
    }

    /**
     * 清理过期的使用记录
     *
     * @return int 删除的记录数
     */
    public static function cleanExpired(): int
    {
        return static::where('period_ends_at', '<', now())->delete();
    }
}
