<?php

namespace App\Models;

use App\Services\CodingStatus\Drivers\Request5ZMCodingStatusDriver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 5ZM 使用日志模型
 *
 * 记录 Request5ZM 驱动的三维度（5小时/周/月）配额消耗记录。
 * 每次请求消耗配额时记录消耗前后的配额快照，支持精确的用量审计。
 *
 * 数据表结构 (coding_5zm_usage_logs):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ account_id               │ bigint unsigned       │ Coding 账户 ID                            │
 * │ channel_id               │ bigint unsigned       │ 渠道 ID                                  │
 * │ request_id               │ varchar(64)           │ 请求 ID                                  │
 * │ requests                 │ int unsigned          │ 请求次数（默认1）                          │
 * │ model                    │ varchar(100)          │ 使用的模型                                │
 * │ model_multiplier         │ decimal(5,2)          │ 模型消耗倍数（默认1.00）                   │
 * │ period_5h                │ varchar(20)           │ 5小时周期标识（如 2026-03-13-0）           │
 * │ period_weekly            │ varchar(10)           │ 周周期标识（如 2026-11）                   │
 * │ period_monthly           │ varchar(7)            │ 月周期标识（如 2026-03）                   │
 * │ quota_before_5h          │ int unsigned          │ 消耗前5小时配额（默认0）                   │
 * │ quota_before_weekly      │ int unsigned          │ 消耗前周配额（默认0）                      │
 * │ quota_before_monthly     │ int unsigned          │ 消耗前月配额（默认0）                      │
 * │ quota_after_5h           │ int unsigned          │ 消耗后5小时配额（默认0）                   │
 * │ quota_after_weekly       │ int unsigned          │ 消耗后周配额（默认0）                      │
 * │ quota_after_monthly      │ int unsigned          │ 消耗后月配额（默认0）                      │
 * │ status                   │ enum                  │ 状态：success/failed/throttled/rejected   │
 * │ metadata                 │ json                  │ 额外元数据                                │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (request_id) - 按请求 ID 查询
 * - INDEX (account_id, created_at) - 按账户+时间查询
 * - INDEX (channel_id, created_at) - 按渠道+时间查询
 * - INDEX (period_5h, account_id) - 5小时周期+账户查询
 * - INDEX (period_weekly, account_id) - 周周期+账户查询
 * - INDEX (period_monthly, account_id) - 月周期+账户查询
 * - INDEX (model, created_at) - 按模型+时间查询
 *
 * 迁移历史：
 * - 2026_03_13_000001: 初始创建表
 *
 * 核心功能：
 * 1. 消耗审计：记录每次配额消耗的前后快照
 * 2. 模型追踪：记录使用的模型和消耗倍数
 * 3. 周期关联：通过 period_* 标识关联到具体配额周期
 * 4. 状态分类：区分成功/失败/限流/拒绝四种状态
 * 5. 用量计算：getUsage*() 方法计算各维度实际消耗量
 *
 * @property int $id 主键
 * @property int $account_id Coding 账户 ID
 * @property int|null $channel_id 渠道 ID
 * @property string|null $request_id 请求 ID
 * @property int $requests 请求次数
 * @property string|null $model 使用的模型
 * @property string $model_multiplier 模型消耗倍数
 * @property string $period_5h 5小时周期标识
 * @property string $period_weekly 周周期标识
 * @property string $period_monthly 月周期标识
 * @property int $quota_before_5h 消耗前5小时配额
 * @property int $quota_before_weekly 消耗前周配额
 * @property int $quota_before_monthly 消耗前月配额
 * @property int $quota_after_5h 消耗后5小时配额
 * @property int $quota_after_weekly 消耗后周配额
 * @property int $quota_after_monthly 消耗后月配额
 * @property string $status 状态
 * @property array|null $metadata 额外元数据
 * @property Carbon $created_at 创建时间
 * @property-read CodingAccount $account 关联的 Coding 账户
 * @property-read Channel|null $channel 关联的渠道
 *
 * @see Coding5ZMQuota 5ZM 配额模型
 * @see CodingAccount Coding 账户模型
 * @see Request5ZMCodingStatusDriver 5ZM 驱动
 */
class Coding5ZMUsageLog extends Model
{
    use HasFactory;

    /**
     * 状态常量：成功
     */
    public const STATUS_SUCCESS = 'success';

    /**
     * 状态常量：失败
     */
    public const STATUS_FAILED = 'failed';

    /**
     * 状态常量：限流
     */
    public const STATUS_THROTTLED = 'throttled';

    /**
     * 状态常量：拒绝
     */
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
     *
     * 用于后台展示的标签颜色映射
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
     *
     * 计算消耗前后配额差值
     */
    public function getUsage5h(): int
    {
        return ($this->quota_after_5h ?? 0) - ($this->quota_before_5h ?? 0);
    }

    /**
     * 获取周的使用量
     *
     * 计算消耗前后配额差值
     */
    public function getUsageWeekly(): int
    {
        return ($this->quota_after_weekly ?? 0) - ($this->quota_before_weekly ?? 0);
    }

    /**
     * 获取月的使用量
     *
     * 计算消耗前后配额差值
     */
    public function getUsageMonthly(): int
    {
        return ($this->quota_after_monthly ?? 0) - ($this->quota_before_monthly ?? 0);
    }
}
