<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 5ZM 使用日志模型
 *
 * 用于存储 Request5ZM 驱动的三维度（5小时/周/月）配额消耗记录
 */
class Coding5ZMUsageLog extends Model
{
    use HasFactory;

    /**
     * 状态常量
     */
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_THROTTLED = 'throttled';

    public const STATUS_REJECTED = 'rejected';

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
        'request_id',
        'requests',
        'model',
        'model_multiplier',
        'period_5h',
        'period_weekly',
        'period_monthly',
        'quota_before_5h',
        'quota_before_weekly',
        'quota_before_monthly',
        'quota_after_5h',
        'quota_after_weekly',
        'quota_after_monthly',
        'status',
        'metadata',
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
            'model_multiplier' => 'decimal:2',
            'metadata' => 'array',
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
     * 获取状态列表
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_SUCCESS => '成功',
            self::STATUS_FAILED => '失败',
            self::STATUS_THROTTLED => '限流',
            self::STATUS_REJECTED => '拒绝',
        ];
    }

    /**
     * 获取状态颜色
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_THROTTLED => 'warning',
            self::STATUS_REJECTED => 'gray',
            default => 'gray',
        };
    }

    /**
     * 获取5小时周期的使用量
     */
    public function getUsage5h(): int
    {
        return ($this->quota_after_5h ?? 0) - ($this->quota_before_5h ?? 0);
    }

    /**
     * 获取周的使用量
     */
    public function getUsageWeekly(): int
    {
        return ($this->quota_after_weekly ?? 0) - ($this->quota_before_weekly ?? 0);
    }

    /**
     * 获取月的使用量
     */
    public function getUsageMonthly(): int
    {
        return ($this->quota_after_monthly ?? 0) - ($this->quota_before_monthly ?? 0);
    }
}
