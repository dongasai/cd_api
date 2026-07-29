<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 使用日志模型
 *
 * 记录每次 API 请求的 Token 消耗、积分消耗和成本等使用详情。
 * 是通用的使用日志模型，支持计费和审计。
 *
 * 数据表结构 (coding_usage_logs):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ account_id               │ bigint unsigned       │ Coding 账户 ID                            │
 * │ channel_id               │ bigint unsigned       │ 渠道 ID                                  │
 * │ request_id               │ varchar(64)           │ 请求 ID                                  │
 * │ requests                 │ int unsigned          │ 请求次数（默认1）                          │
 * │ tokens_input             │ int unsigned          │ 输入 Token 数（默认0）                     │
 * │ tokens_output            │ int unsigned          │ 输出 Token 数（默认0）                     │
 * │ prompts                  │ int unsigned          │ Prompt 次数（默认0）                      │
 * │ credits                  │ decimal(10,4)         │ 消耗积分（默认0.0000）                     │
 * │ cost                     │ decimal(10,6)         │ 金额成本（默认0.000000）                   │
 * │ model                    │ varchar(100)          │ 使用的模型                                │
 * │ model_multiplier         │ decimal(5,2)          │ 模型消耗倍数（默认1.00）                   │
 * │ status                   │ enum                  │ 状态：success/failed/throttled/rejected   │
 * │ metadata                 │ json                  │ 额外元数据                                │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (account_id, created_at) - 按账户+时间查询
 * - INDEX (channel_id, created_at) - 按渠道+时间查询
 * - INDEX (model, created_at) - 按模型+时间查询
 * - INDEX (request_id) - 按请求 ID 查询
 *
 * 迁移历史：
 * - 2026_03_07_100002: 初始创建表
 *
 * 核心功能：
 * 1. 用量审计：记录每次请求的 Token 消耗详情
 * 2. 计费支持：credits 和 cost 字段支持积分和金额计算
 * 3. 模型追踪：记录使用的模型和消耗倍数
 * 4. 状态分类：区分成功/失败/限流/拒绝四种状态
 *
 * @property int $id 主键
 * @property int $account_id Coding 账户 ID
 * @property int|null $channel_id 渠道 ID
 * @property string|null $request_id 请求 ID
 * @property int $requests 请求次数
 * @property int $tokens_input 输入 Token 数
 * @property int $tokens_output 输出 Token 数
 * @property int $prompts Prompt 次数
 * @property string $credits 消耗积分
 * @property string $cost 金额成本
 * @property string|null $model 使用的模型
 * @property string $model_multiplier 模型消耗倍数
 * @property string $status 状态
 * @property array|null $metadata 额外元数据
 * @property Carbon $created_at 创建时间
 * @property-read CodingAccount $account 关联的 Coding 账户
 * @property-read Channel|null $channel 关联的渠道
 *
 * @see CodingAccount Coding 账户模型
 * @see CodingSlidingUsageLog 滑动窗口使用日志
 */
class CodingUsageLog extends Model
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
        'tokens_input',
        'tokens_output',
        'prompts',
        'credits',
        'cost',
        'model',
        'model_multiplier',
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
            'credits' => 'decimal:4',
            'cost' => 'decimal:6',
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
     * 获取总Token数
     */
    public function getTotalTokens(): int
    {
        return $this->tokens_input + $this->tokens_output;
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
}
