<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Coding 滑动窗口模型
 *
 * 管理滑动窗口配额的时间窗口，支持多种窗口类型。
 * 每个账户每种窗口类型维护一个活跃窗口，窗口过期后标记为 expired。
 *
 * 数据表结构 (coding_sliding_windows):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ account_id               │ bigint unsigned       │ Coding 账户 ID                            │
 * │ window_type              │ varchar(20)           │ 窗口类型：5h/1d/7d/30d                    │
 * │ window_seconds           │ int unsigned          │ 窗口时长（秒）                            │
 * │ started_at               │ timestamp             │ 窗口开始时间                              │
 * │ ends_at                  │ timestamp             │ 窗口结束时间                              │
 * │ status                   │ varchar(20)           │ 状态：active/expired（默认 active）        │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * │ updated_at               │ timestamp             │ 更新时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (account_id, window_type, status) - 按账户+类型+状态查询活跃窗口
 * - INDEX (ends_at) - 按结束时间查询（过期清理）
 *
 * 迁移历史：
 * - 2026_03_08_124957: 初始创建表
 *
 * 核心功能：
 * 1. 滑动窗口管理：4 种窗口类型（5h/1d/7d/30d）
 * 2. 自动过期：ends_at 到期后标记为 expired
 * 3. 窗口关联：通过 window_id 关联使用日志
 *
 * @property int $id 主键
 * @property int $account_id Coding 账户 ID
 * @property string $window_type 窗口类型
 * @property int $window_seconds 窗口时长（秒）
 * @property Carbon $started_at 窗口开始时间
 * @property Carbon $ends_at 窗口结束时间
 * @property string $status 状态
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property-read CodingAccount $account 关联的 Coding 账户
 *
 * @see CodingSlidingUsageLog 滑动窗口使用日志
 * @see CodingAccount Coding 账户模型
 */
class CodingSlidingWindow extends Model
{
    use HasFactory;

    /**
     * 状态常量
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    /**
     * 窗口类型常量
     */
    public const TYPE_5H = '5h';

    public const TYPE_1D = '1d';

    public const TYPE_7D = '7d';

    public const TYPE_30D = '30d';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'window_type',
        'window_seconds',
        'started_at',
        'ends_at',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'window_seconds' => 'integer',
        ];
    }

    /**
     * 关联 CodingAccount
     *
     * @see CodingAccount
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class, 'account_id');
    }

    /**
     * 关联使用记录
     *
     * @see CodingSlidingUsageLog
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(CodingSlidingUsageLog::class, 'window_id');
    }

    /**
     * 检查窗口是否过期
     */
    public function isExpired(): bool
    {
        return $this->ends_at->isPast();
    }

    /**
     * 检查窗口是否活跃
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    /**
     * 标记为过期
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * 获取窗口类型对应的秒数
     */
    public static function getTypeSeconds(string $type): int
    {
        return match ($type) {
            self::TYPE_5H => 5 * 3600,
            self::TYPE_1D => 24 * 3600,
            self::TYPE_7D => 7 * 24 * 3600,
            self::TYPE_30D => 30 * 24 * 3600,
            default => 5 * 3600,
        };
    }
}
