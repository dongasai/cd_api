<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
