<?php

namespace App\Models;

use App\Services\ChannelError\ChannelErrorHandlingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 渠道错误处理日志模型
 *
 * 记录渠道错误处理的历史记录，用于追踪和分析错误处理行为。
 *
 * 数据表结构 (channel_error_handling_logs):
 * ┌──────────────────────────┬─────────────┬───────────────────────────────────────────┐
 * │ 字段名                    │ 类型        │ 说明                                       │
 * ├──────────────────────────┼─────────────┼───────────────────────────────────────────┤
 * │ id                       │ bigint      │ 主键，自增                                  │
 * │ channel_id               │ bigint      │ 渠道 ID                                    │
 * │ account_id               │ bigint      │ 账户 ID                                    │
 * │ rule_id                  │ bigint      │ 规则 ID                                    │
 * │ error_status_code        │ smallint    │ HTTP 状态码                                 │
 * │ error_type               │ varchar(255)│ 错误类型                                    │
 * │ error_message            │ text        │ 错误消息                                    │
 * │ action_taken             │ varchar(255)│ 执行的动作                                  │
 * │ pause_duration_minutes    │ int         │ 暂停时长（分钟）                             │
 * │ triggered_by             │ varchar(255)│ 触发方式：auto/manual                       │
 * │ user_id                  │ bigint      │ 操作用户 ID（手动触发时）                    │
 * │ created_at               │ timestamp   │ 创建时间                                    │
 * │ updated_at               │ timestamp   │ 更新时间                                    │
 * └──────────────────────────┴─────────────┴───────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (channel_id, created_at) - 渠道+时间复合索引
 * - INDEX (account_id, created_at) - 账户+时间复合索引
 * - INDEX (created_at) - 时间索引
 *
 * 迁移历史：
 * - 2026_04_06_140904: 初始创建表
 *
 * 核心功能：
 * 1. 错误追踪：记录渠道错误的详细信息（状态码、类型、消息）
 * 2. 处理审计：记录执行的动作和参数
 * 3. 触发来源：区分自动触发和手动触发
 * 4. 时间分析：通过时间索引支持历史查询和统计
 *
 * @property int $id 主键
 * @property int|null $channel_id 渠道 ID
 * @property int|null $account_id 账户 ID
 * @property int|null $rule_id 规则 ID
 * @property int|null $error_status_code HTTP 状态码
 * @property string|null $error_type 错误类型
 * @property string|null $error_message 错误消息
 * @property string $action_taken 执行的动作
 * @property int|null $pause_duration_minutes 暂停时长（分钟）
 * @property string $triggered_by 触发方式：auto/manual
 * @property int|null $user_id 操作用户 ID（手动触发时）
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property-read Channel|null $channel 关联的渠道
 * @property-read CodingAccount|null $account 关联的账户
 * @property-read ChannelErrorRule|null $rule 关联的规则
 * @property-read User|null $user 关联的用户
 *
 * @see ChannelErrorRule 错误规则模型
 * @see ChannelErrorHandlingService 错误处理服务
 */
class ChannelErrorHandlingLog extends Model
{
    /**
     * 触发方式常量：自动触发
     */
    public const TRIGGERED_BY_AUTO = 'auto';

    /**
     * 触发方式常量：手动触发
     */
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
        return $this->belongsTo(User::class);
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
     *
     * @param  array  $data  日志数据
     *
     * @see ChannelErrorHandlingService::handle() 自动处理时调用
     */
    public static function logAutoHandling(array $data): self
    {
        return static::create(array_merge($data, [
            'triggered_by' => self::TRIGGERED_BY_AUTO,
        ]));
    }

    /**
     * 记录手动触发的日志
     *
     * @param  array  $data  日志数据
     * @param  int  $userId  操作用户 ID
     */
    public static function logManualHandling(array $data, int $userId): self
    {
        return static::create(array_merge($data, [
            'triggered_by' => self::TRIGGERED_BY_MANUAL,
            'user_id' => $userId,
        ]));
    }
}
