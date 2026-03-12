<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 5ZM 配额模型
 *
 * 存储 Request5ZM 驱动的配额配置和使用量
 */
class Coding5ZMQuota extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'limit_5h',
        'limit_weekly',
        'limit_monthly',
        'used_5h',
        'used_weekly',
        'used_monthly',
        'period_5h',
        'period_weekly',
        'period_monthly',
        'threshold_warning',
        'threshold_critical',
        'threshold_disable',
        'period_offset',
        'reset_day',
        'last_sync_at',
        'last_usage_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'threshold_warning' => 'decimal:3',
            'threshold_critical' => 'decimal:3',
            'threshold_disable' => 'decimal:3',
            'last_sync_at' => 'datetime',
            'last_usage_at' => 'datetime',
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
     * 获取5小时周期的使用率
     */
    public function getRate5h(): float
    {
        if ($this->limit_5h <= 0) {
            return 0.0;
        }

        return round($this->used_5h / $this->limit_5h, 4);
    }

    /**
     * 获取周的使用率
     */
    public function getRateWeekly(): float
    {
        if ($this->limit_weekly <= 0) {
            return 0.0;
        }

        return round($this->used_weekly / $this->limit_weekly, 4);
    }

    /**
     * 获取月的使用率
     */
    public function getRateMonthly(): float
    {
        if ($this->limit_monthly <= 0) {
            return 0.0;
        }

        return round($this->used_monthly / $this->limit_monthly, 4);
    }

    /**
     * 获取最大使用率
     */
    public function getMaxRate(): float
    {
        return max($this->getRate5h(), $this->getRateWeekly(), $this->getRateMonthly());
    }

    /**
     * 检查配额是否充足
     */
    public function hasQuota(int $requests = 1): bool
    {
        return $this->used_5h + $requests <= $this->limit_5h
            && $this->used_weekly + $requests <= $this->limit_weekly
            && $this->used_monthly + $requests <= $this->limit_monthly;
    }

    /**
     * 消耗配额
     */
    public function consume(int $requests): void
    {
        $this->used_5h += $requests;
        $this->used_weekly += $requests;
        $this->used_monthly += $requests;
        $this->last_usage_at = now();
        $this->save();
    }

    /**
     * 重置指定维度的使用量
     */
    public function reset(string $dimension): void
    {
        match ($dimension) {
            '5h' => $this->update(['used_5h' => 0]),
            'weekly' => $this->update(['used_weekly' => 0]),
            'monthly' => $this->update(['used_monthly' => 0]),
        };
    }
}
