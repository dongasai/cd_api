<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 渠道错误处理日志模型
 *
 * 记录错误处理的历史记录
 */
class ChannelErrorHandlingLog extends Model
{
    /**
     * 触发方式常量
     */
    public const TRIGGERED_BY_AUTO = 'auto';

    public const TRIGGERED_BY_MANUAL = 'manual';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'account_id',
        'rule_id',
        'error_status_code',
        'error_type',
        'error_message',
        'action_taken',
        'pause_duration_minutes',
        'triggered_by',
        'user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 关联的渠道
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * 关联的账户
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class, 'account_id');
    }

    /**
     * 关联的规则
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ChannelErrorRule::class, 'rule_id');
    }

    /**
     * 关联的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * 获取触发方式选项
     *
     * @return array<string, string>
     */
    public static function getTriggeredByOptions(): array
    {
        return [
            self::TRIGGERED_BY_AUTO => '自动触发',
            self::TRIGGERED_BY_MANUAL => '手动触发',
        ];
    }

    /**
     * 记录自动触发的日志
     */
    public static function logAutoHandling(array $data): self
    {
        return static::create(array_merge($data, [
            'triggered_by' => self::TRIGGERED_BY_AUTO,
        ]));
    }

    /**
     * 记录手动触发的日志
     */
    public static function logManualHandling(array $data, int $userId): self
    {
        return static::create(array_merge($data, [
            'triggered_by' => self::TRIGGERED_BY_MANUAL,
            'user_id' => $userId,
        ]));
    }
}
