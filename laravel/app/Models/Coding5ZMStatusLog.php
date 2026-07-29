<?php

namespace App\Models;

use App\Services\CodingStatus\Drivers\Request5ZMCodingStatusDriver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 5ZM 状态日志模型
 *
 * 记录 Request5ZM 驱动的三维度配额状态变更历史。
 * 每次账户状态因配额变化而切换时，记录变更前后的状态和各维度配额快照。
 *
 * 数据表结构 (coding_5zm_status_logs):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ account_id               │ bigint unsigned       │ Coding 账户 ID                            │
 * │ channel_id               │ bigint unsigned       │ 关联渠道 ID                               │
 * │ from_status              │ varchar(20)           │ 原状态                                    │
 * │ to_status                │ varchar(20)           │ 新状态                                    │
 * │ reason                   │ varchar(255)          │ 变更原因                                  │
 * │ quota_5h_used            │ int unsigned          │ 5小时周期已用（默认0）                     │
 * │ quota_5h_limit           │ int unsigned          │ 5小时周期限额（默认0）                     │
 * │ quota_5h_rate            │ decimal(5,4)          │ 5小时周期使用率（默认0.0000）              │
 * │ quota_weekly_used        │ int unsigned          │ 周已用（默认0）                           │
 * │ quota_weekly_limit       │ int unsigned          │ 周限额（默认0）                           │
 * │ quota_weekly_rate        │ decimal(5,4)          │ 周使用率（默认0.0000）                    │
 * │ quota_monthly_used       │ int unsigned          │ 月已用（默认0）                           │
 * │ quota_monthly_limit      │ int unsigned          │ 月限额（默认0）                           │
 * │ quota_monthly_rate       │ decimal(5,4)          │ 月使用率（默认0.0000）                    │
 * │ triggered_by             │ enum                  │ 触发方式：system/manual/api/sync/         │
 * │                          │                       │           quota_exhausted/quota_recovered │
 * │ user_id                  │ bigint unsigned       │ 操作用户 ID                               │
 * │ period_5h                │ varchar(20)           │ 5小时周期标识                              │
 * │ period_weekly            │ varchar(10)           │ 周周期标识                                │
 * │ period_monthly           │ varchar(7)            │ 月周期标识                                │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (account_id, created_at) - 按账户+时间查询
 * - INDEX (channel_id, created_at) - 按渠道+时间查询
 * - INDEX (from_status, to_status) - 按状态变更查询
 * - INDEX (triggered_by, created_at) - 按触发方式+时间查询
 * - INDEX (user_id) - 按用户查询（外键）
 *
 * 迁移历史：
 * - 2026_03_13_000002: 初始创建表
 *
 * 核心功能：
 * 1. 状态变更追踪：记录账户状态切换的完整上下文
 * 2. 配额快照：记录变更时三个维度的配额使用情况
 * 3. 触发溯源：区分系统/手动/API/同步/配额耗尽/配额恢复等触发方式
 * 4. 维度分析：getPrimaryDimension() 识别触发变更的主要维度
 *
 * @property int $id 主键
 * @property int $account_id Coding 账户 ID
 * @property int|null $channel_id 关联渠道 ID
 * @property string $from_status 原状态
 * @property string $to_status 新状态
 * @property string|null $reason 变更原因
 * @property int $quota_5h_used 5小时周期已用
 * @property int $quota_5h_limit 5小时周期限额
 * @property string $quota_5h_rate 5小时周期使用率
 * @property int $quota_weekly_used 周已用
 * @property int $quota_weekly_limit 周限额
 * @property string $quota_weekly_rate 周使用率
 * @property int $quota_monthly_used 月已用
 * @property int $quota_monthly_limit 月限额
 * @property string $quota_monthly_rate 月使用率
 * @property string $triggered_by 触发方式
 * @property int|null $user_id 操作用户 ID
 * @property string|null $period_5h 5小时周期标识
 * @property string|null $period_weekly 周周期标识
 * @property string|null $period_monthly 月周期标识
 * @property Carbon $created_at 创建时间
 * @property-read CodingAccount $account 关联的 Coding 账户
 * @property-read Channel|null $channel 关联的渠道
 * @property-read User|null $user 操作用户
 *
 * @see Coding5ZMQuota 5ZM 配额模型
 * @see CodingAccount Coding 账户模型
 * @see Request5ZMCodingStatusDriver 5ZM 驱动
 */
class Coding5ZMStatusLog extends Model
{
    use HasFactory;

    /**
     * 触发方式常量：系统自动
     */
    public const TRIGGERED_BY_SYSTEM = 'system';

    /**
     * 触发方式常量：手动操作
     */
    public const TRIGGERED_BY_MANUAL = 'manual';

    /**
     * 触发方式常量：API 调用
     */
    public const TRIGGERED_BY_API = 'api';

    /**
     * 触发方式常量：同步操作
     */
    public const TRIGGERED_BY_SYNC = 'sync';

    /**
     * 触发方式常量：配额耗尽
     */
    public const TRIGGERED_BY_QUOTA_EXHAUSTED = 'quota_exhausted';

    /**
     * 触发方式常量：配额恢复
     */
    public const TRIGGERED_BY_QUOTA_RECOVERED = 'quota_recovered';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'channel_id',
        'from_status',
        'to_status',
        'reason',
        'quota_5h_used',
        'quota_5h_limit',
        'quota_5h_rate',
        'quota_weekly_used',
        'quota_weekly_limit',
        'quota_weekly_rate',
        'quota_monthly_used',
        'quota_monthly_limit',
        'quota_monthly_rate',
        'triggered_by',
        'user_id',
        'period_5h',
        'period_weekly',
        'period_monthly',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quota_5h_rate' => 'decimal:4',
            'quota_weekly_rate' => 'decimal:4',
            'quota_monthly_rate' => 'decimal:4',
            'created_at' => 'datetime',
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
     * 关联的渠道
     *
     * @see Channel
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * 关联的用户
     *
     * @see User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 获取触发方式列表
     *
     * @return array<string, string>
     */
    public static function getTriggeredBys(): array
    {
        return [
            self::TRIGGERED_BY_SYSTEM => '系统',
            self::TRIGGERED_BY_MANUAL => '手动',
            self::TRIGGERED_BY_API => 'API',
            self::TRIGGERED_BY_SYNC => '同步',
            self::TRIGGERED_BY_QUOTA_EXHAUSTED => '配额耗尽',
            self::TRIGGERED_BY_QUOTA_RECOVERED => '配额恢复',
        ];
    }

    /**
     * 获取触发方式颜色
     *
     * 用于后台展示的标签颜色映射
     */
    public function getTriggeredByColor(): string
    {
        return match ($this->triggered_by) {
            self::TRIGGERED_BY_SYSTEM => 'primary',
            self::TRIGGERED_BY_MANUAL => 'warning',
            self::TRIGGERED_BY_API => 'info',
            self::TRIGGERED_BY_SYNC => 'success',
            self::TRIGGERED_BY_QUOTA_EXHAUSTED => 'danger',
            self::TRIGGERED_BY_QUOTA_RECOVERED => 'success',
            default => 'secondary',
        };
    }

    /**
     * 获取状态变更描述
     *
     * 将状态码转换为中文描述，格式：原状态 → 新状态
     *
     * @see CodingAccount::getStatuses() 状态码映射
     */
    public function getStatusChangeDescription(): string
    {
        $statuses = CodingAccount::getStatuses();
        $from = $statuses[$this->from_status] ?? $this->from_status;
        $to = $statuses[$this->to_status] ?? $this->to_status;

        return "{$from} → {$to}";
    }

    /**
     * 获取主要触发的维度
     *
     * 取使用率最高的维度作为主要触发维度
     */
    public function getPrimaryDimension(): string
    {
        $rates = [
            '5h' => $this->quota_5h_rate,
            'weekly' => $this->quota_weekly_rate,
            'monthly' => $this->quota_monthly_rate,
        ];

        arsort($rates);
        $primary = array_key_first($rates);

        $labels = [
            '5h' => '5小时周期',
            'weekly' => '周',
            'monthly' => '月',
        ];

        return $labels[$primary] ?? $primary;
    }
}
