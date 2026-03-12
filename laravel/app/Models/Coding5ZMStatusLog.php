<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 5ZM 状态日志模型
 *
 * 记录 Request5ZM 驱动的三维度状态变更历史
 */
class Coding5ZMStatusLog extends Model
{
    use HasFactory;

    /**
     * 触发方式常量
     */
    public const TRIGGERED_BY_SYSTEM = 'system';

    public const TRIGGERED_BY_MANUAL = 'manual';

    public const TRIGGERED_BY_API = 'api';

    public const TRIGGERED_BY_SYNC = 'sync';

    public const TRIGGERED_BY_QUOTA_EXHAUSTED = 'quota_exhausted';

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
     * 关联的Coding账户
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class, 'account_id');
    }

    /**
     * 关联的渠道
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * 关联的用户
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
