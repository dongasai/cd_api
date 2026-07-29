<?php

namespace App\Models;

use App\Services\CodingStatus\Drivers\Request5ZMCodingStatusDriver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 5ZM 配额模型
 *
 * 存储 Request5ZM 驱动的三维度（5小时/周/月）配额配置和使用量。
 * 从 coding_accounts 表的 quota_config/quota_cached 字段迁移而来，提供更灵活的配额管理。
 * 每个账户一条记录，通过 account_id 唯一约束保证。
 *
 * 数据表结构 (coding_5zm_quotas):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ account_id               │ bigint unsigned       │ Coding 账户 ID（唯一）                    │
 * │ limit_5h                 │ int unsigned          │ 5小时周期限额（默认300）                   │
 * │ limit_weekly             │ int unsigned          │ 周限额（默认1000）                        │
 * │ limit_monthly            │ int unsigned          │ 月限额（默认5000）                        │
 * │ used_5h                  │ int unsigned          │ 5小时周期已用（默认0）                     │
 * │ used_weekly              │ int unsigned          │ 周已用（默认0）                           │
 * │ used_monthly             │ int unsigned          │ 月已用（默认0）                           │
 * │ period_5h                │ varchar(20)           │ 当前5小时周期标识                          │
 * │ period_weekly            │ varchar(10)           │ 当前周周期标识                             │
 * │ period_monthly           │ varchar(7)            │ 当前月周期标识                             │
 * │ threshold_warning        │ decimal(4,3)          │ 警告阈值（默认0.800）                      │
 * │ threshold_critical       │ decimal(4,3)          │ 临界阈值（默认0.900）                      │
 * │ threshold_disable        │ decimal(4,3)          │ 禁用阈值（默认0.950）                      │
 * │ period_offset            │ smallint unsigned     │ 5小时周期偏移量（秒，默认0）               │
 * │ reset_day                │ tinyint unsigned      │ 月重置日期（默认1）                        │
 * │ last_sync_at             │ timestamp             │ 最后同步时间                              │
 * │ last_usage_at            │ timestamp             │ 最后消耗时间                              │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * │ updated_at               │ timestamp             │ 更新时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - UNIQUE (account_id) - 账户唯一约束
 * - INDEX (period_5h, account_id) - 5小时周期+账户查询
 * - INDEX (period_weekly, account_id) - 周周期+账户查询
 * - INDEX (period_monthly, account_id) - 月周期+账户查询
 *
 * 迁移历史：
 * - 2026_03_13_000003: 初始创建表，从 coding_accounts 迁移 quota_config/quota_cached 字段
 *
 * 核心功能：
 * 1. 三维度配额：5小时/周/月三个维度独立限额和使用量
 * 2. 阈值告警：warning → critical → disable 三级阈值
 * 3. 周期管理：period_* 标识当前周期，支持周期偏移和自定义重置日
 * 4. 使用率计算：getRate*() 方法计算各维度使用率
 * 5. 配额消耗：consume() 方法原子性增加三维度使用量
 *
 * @property int $id 主键
 * @property int $account_id Coding 账户 ID（唯一）
 * @property int $limit_5h 5小时周期限额
 * @property int $limit_weekly 周限额
 * @property int $limit_monthly 月限额
 * @property int $used_5h 5小时周期已用
 * @property int $used_weekly 周已用
 * @property int $used_monthly 月已用
 * @property string|null $period_5h 当前5小时周期标识
 * @property string|null $period_weekly 当前周周期标识
 * @property string|null $period_monthly 当前月周期标识
 * @property string $threshold_warning 警告阈值
 * @property string $threshold_critical 临界阈值
 * @property string $threshold_disable 禁用阈值
 * @property int $period_offset 5小时周期偏移量（秒）
 * @property int $reset_day 月重置日期
 * @property Carbon|null $last_sync_at 最后同步时间
 * @property Carbon|null $last_usage_at 最后消耗时间
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property-read CodingAccount $account 关联的 Coding 账户
 *
 * @see CodingAccount Coding 账户模型
 * @see Coding5ZMUsageLog 5ZM 使用日志
 * @see Coding5ZMStatusLog 5ZM 状态日志
 * @see Request5ZMCodingStatusDriver 5ZM 驱动
 */
class Coding5ZMQuota extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'limit_5h',
        'limit_weekly',
        'limit_monthly',
        'used_5h',
        'used_weekly',
        'used_monthly',
        'period_5h',
        'period_weekly',
        'period_monthly',
        'threshold_warning',
        'threshold_critical',
        'threshold_disable',
        'period_offset',
        'reset_day',
        'last_sync_at',
        'last_usage_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'threshold_warning' => 'decimal:3',
            'threshold_critical' => 'decimal:3',
            'threshold_disable' => 'decimal:3',
            'last_sync_at' => 'datetime',
            'last_usage_at' => 'datetime',
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
     * 获取5小时周期的使用率
     *
     * 返回 0~1 之间的浮点数，limit 为 0 时返回 0.0
     */
    public function getRate5h(): float
    {
        if ($this->limit_5h <= 0) {
            return 0.0;
        }

        return round($this->used_5h / $this->limit_5h, 4);
    }

    /**
     * 获取周的使用率
     *
     * 返回 0~1 之间的浮点数，limit 为 0 时返回 0.0
     */
    public function getRateWeekly(): float
    {
        if ($this->limit_weekly <= 0) {
            return 0.0;
        }

        return round($this->used_weekly / $this->limit_weekly, 4);
    }

    /**
     * 获取月的使用率
     *
     * 返回 0~1 之间的浮点数，limit 为 0 时返回 0.0
     */
    public function getRateMonthly(): float
    {
        if ($this->limit_monthly <= 0) {
            return 0.0;
        }

        return round($this->used_monthly / $this->limit_monthly, 4);
    }

    /**
     * 获取最大使用率
     *
     * 取三个维度中的最大使用率，用于判断账户状态
     *
     * @see Request5ZMCodingStatusDriver 使用此方法判断阈值
     */
    public function getMaxRate(): float
    {
        return max($this->getRate5h(), $this->getRateWeekly(), $this->getRateMonthly());
    }

    /**
     * 检查配额是否充足
     *
     * 三个维度均需满足：已用 + 请求数 <= 限额
     */
    public function hasQuota(int $requests = 1): bool
    {
        return $this->used_5h + $requests <= $this->limit_5h
            && $this->used_weekly + $requests <= $this->limit_weekly
            && $this->used_monthly + $requests <= $this->limit_monthly;
    }

    /**
     * 消耗配额
     *
     * 同时增加三个维度的使用量，更新最后消耗时间
     */
    public function consume(int $requests): void
    {
        $this->used_5h += $requests;
        $this->used_weekly += $requests;
        $this->used_monthly += $requests;
        $this->last_usage_at = now();
        $this->save();
    }

    /**
     * 重置指定维度的使用量
     *
     * @param  string  $dimension  维度：'5h' / 'weekly' / 'monthly'
     */
    public function reset(string $dimension): void
    {
        match ($dimension) {
            '5h' => $this->update(['used_5h' => 0]),
            'weekly' => $this->update(['used_weekly' => 0]),
            'monthly' => $this->update(['used_monthly' => 0]),
        };
    }
}
