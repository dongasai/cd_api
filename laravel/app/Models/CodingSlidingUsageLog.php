<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class, 'account_id');
    }

    /**
     * 关联滑动窗口
     */
    public function window(): BelongsTo
    {
        return $this->belongsTo(CodingSlidingWindow::class, 'window_id');
    }

    /**
     * 关联渠道
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
