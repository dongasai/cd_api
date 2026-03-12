<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding配额使用量模型
 *
 * 用于存储各账户在不同周期的配额使用量，替代 Redis 存储
 */
class CodingQuotaUsage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'metric',
        'period_key',
        'period_type',
        'used',
        'period_starts_at',
        'period_ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used' => 'integer',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
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
     * 获取或创建指定账户、指标、周期的使用记录
     *
     * @param  int  $accountId  账户ID
     * @param  string  $metric  指标名称
     * @param  string  $periodKey  周期标识
     * @param  string  $periodType  周期类型
     * @param  array  $periodInfo  周期信息（starts_at, ends_at）
     */
    public static function getOrCreateForPeriod(
        int $accountId,
        string $metric,
        string $periodKey,
        string $periodType,
        array $periodInfo = []
    ): static {
        return static::firstOrCreate(
            [
                'account_id' => $accountId,
                'metric' => $metric,
                'period_key' => $periodKey,
            ],
            [
                'period_type' => $periodType,
                'used' => 0,
                'period_starts_at' => $periodInfo['starts_at'] ?? null,
                'period_ends_at' => $periodInfo['ends_at'] ?? null,
            ]
        );
    }

    /**
     * 增加使用量
     *
     * @param  int  $amount  增加的数量
     * @return int 增加后的总使用量
     */
    public function incrementUsage(int $amount): int
    {
        $this->increment('used', $amount);

        return $this->used;
    }

    /**
     * 清理过期的使用记录
     *
     * @return int 删除的记录数
     */
    public static function cleanExpired(): int
    {
        return static::where('period_ends_at', '<', now())->delete();
    }
}
