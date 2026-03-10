<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodingStatusLog extends Model
{
    use HasFactory;

    /**
     * 触发方式常量
     */
    public const TRIGGERED_BY_SYSTEM = 'system';

    public const TRIGGERED_BY_MANUAL = 'manual';

    public const TRIGGERED_BY_API = 'api';

    public const TRIGGERED_BY_SYNC = 'sync';

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
        'quota_snapshot',
        'triggered_by',
        'user_id',
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
            'quota_snapshot' => 'array',
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
            default => 'gray',
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
}
