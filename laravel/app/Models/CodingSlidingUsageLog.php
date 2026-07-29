<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 滑动窗口使用日志模型
 *
 * 记录滑动窗口配额的每次消耗详情，包括 Token 使用量和请求信息。
 * 与 CodingSlidingWindow 通过 window_id 关联。
 *
 * 数据表结构 (coding_sliding_usage_logs):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ account_id               │ bigint unsigned       │ Coding 账户 ID                            │
 * │ window_id                │ bigint unsigned       │ 关联的滑动窗口 ID                          │
 * │ channel_id               │ bigint unsigned       │ 渠道 ID                                  │
 * │ request_id               │ varchar(64)           │ 请求 ID                                  │
 * │ requests                 │ int unsigned          │ 请求次数（默认0）                          │
 * │ tokens_input             │ bigint unsigned       │ 输入 Token 数（默认0）                     │
 * │ tokens_output            │ bigint unsigned       │ 输出 Token 数（默认0）                     │
 * │ tokens_total             │ bigint unsigned       │ 总 Token 数（默认0）                       │
 * │ model                    │ varchar(100)          │ 使用的模型                                │
 * │ model_multiplier         │ decimal(5,2)          │ 模型消耗倍数（默认1.00）                   │
 * │ status                   │ varchar(20)           │ 状态：success/failed/throttled/rejected   │
 * │ metadata                 │ json                  │ 额外元数据                                │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (account_id, created_at) - 按账户+时间查询
 * - INDEX (window_id, created_at) - 按窗口+时间查询
 * - INDEX (channel_id) - 按渠道查询
 * - INDEX (request_id) - 按请求 ID 查询
 *
 * 迁移历史：
 * - 2026_03_08_124958: 初始创建表
 *
 * @property int $id 主键
 * @property int $account_id Coding 账户 ID
 * @property int|null $window_id 关联的滑动窗口 ID
 * @property int|null $channel_id 渠道 ID
 * @property string|null $request_id 请求 ID
 * @property int $requests 请求次数
 * @property int $tokens_input 输入 Token 数
 * @property int $tokens_output 输出 Token 数
 * @property int $tokens_total 总 Token 数
 * @property string|null $model 使用的模型
 * @property string $model_multiplier 模型消耗倍数
 * @property string $status 状态
 * @property array|null $metadata 额外元数据
 * @property Carbon $created_at 创建时间
 * @property-read CodingAccount $account 关联的 Coding 账户
 * @property-read CodingSlidingWindow|null $window 关联的滑动窗口
 * @property-read Channel|null $channel 关联的渠道
 *
 * @see CodingSlidingWindow 滑动窗口模型
 * @see CodingAccount Coding 账户模型
 */
class CodingSlidingUsageLog extends Model
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
        'window_id',
        'channel_id',
        'request_id',
        'requests',
        'tokens_input',
        'tokens_output',
        'tokens_total',
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
            'requests' => 'integer',
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'tokens_total' => 'integer',
            'model_multiplier' => 'decimal:2',
            'metadata' => 'array',
            'created_at' => 'datetime',
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
     * 关联滑动窗口
     *
     * @see CodingSlidingWindow
     */
    public function window(): BelongsTo
    {
        return $this->belongsTo(CodingSlidingWindow::class, 'window_id');
    }

    /**
     * 关联渠道
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
}
