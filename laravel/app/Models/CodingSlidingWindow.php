<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodingSlidingWindow extends Model
{
    use HasFactory;

    /**
     * 状态常量
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    /**
     * 窗口类型常量
     */
    public const TYPE_5H = '5h';

    public const TYPE_1D = '1d';

    public const TYPE_7D = '7d';

    public const TYPE_30D = '30d';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'window_type',
        'window_seconds',
        'started_at',
        'ends_at',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'window_seconds' => 'integer',
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
     * 关联使用记录
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(CodingSlidingUsageLog::class, 'window_id');
    }

    /**
     * 检查窗口是否过期
     */
    public function isExpired(): bool
    {
        return $this->ends_at->isPast();
    }

    /**
     * 检查窗口是否活跃
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    /**
     * 标记为过期
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * 获取窗口类型对应的秒数
     */
    public static function getTypeSeconds(string $type): int
    {
        return match ($type) {
            self::TYPE_5H => 5 * 3600,
            self::TYPE_1D => 24 * 3600,
            self::TYPE_7D => 7 * 24 * 3600,
            self::TYPE_30D => 30 * 24 * 3600,
            default => 5 * 3600,
        };
    }
}
