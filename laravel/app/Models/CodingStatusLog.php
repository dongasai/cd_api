<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 状态变更日志模型
 *
 * 记录 Coding 账户的状态变更历史，包括变更前后的状态、原因、配额快照等。
 * 与 Coding5ZMStatusLog 不同，此模型为通用状态日志，不包含三维度配额快照。
 *
 * 数据表结构 (coding_status_logs):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ account_id               │ bigint unsigned       │ Coding 账户 ID                            │
 * │ channel_id               │ bigint unsigned       │ 关联渠道 ID                               │
 * │ from_status              │ varchar(20)           │ 原状态                                    │
 * │ to_status                │ varchar(20)           │ 新状态                                    │
 * │ reason                   │ varchar(255)          │ 变更原因                                  │
 * │ quota_snapshot           │ json                  │ 配额快照                                  │
 * │ triggered_by             │ enum                  │ 触发方式：system/manual/api/sync          │
 * │ user_id                  │ bigint unsigned       │ 操作用户 ID                               │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (account_id, created_at) - 按账户+时间查询
 * - INDEX (channel_id, created_at) - 按渠道+时间查询
 * - INDEX (from_status, to_status) - 按状态变更查询
 *
 * 迁移历史：
 * - 2026_03_07_100003: 初始创建表
 *
 * @property int $id 主键
 * @property int $account_id Coding 账户 ID
 * @property int|null $channel_id 关联渠道 ID
 * @property string $from_status 原状态
 * @property string $to_status 新状态
 * @property string|null $reason 变更原因
 * @property array|null $quota_snapshot 配额快照
 * @property string $triggered_by 触发方式
 * @property int|null $user_id 操作用户 ID
 * @property Carbon $created_at 创建时间
 * @property-read CodingAccount $account 关联的 Coding 账户
 * @property-read Channel|null $channel 关联的渠道
 * @property-read User|null $user 操作用户
 *
 * @see CodingAccount Coding 账户模型
 * @see Coding5ZMStatusLog 5ZM 专用状态日志（含三维度快照）
 */
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
