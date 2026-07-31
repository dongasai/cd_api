<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coding 可用时段模型
 *
 * 管理 Coding 账户的可用时段配置，支持多时段、星期筛选、跨午夜时段。
 *
 * @property int $id 主键
 * @property int $coding_account_id 关联 Coding 账户 ID
 * @property string $start_time 开始时间
 * @property string $end_time 结束时间
 * @property array|null $weekdays 适用星期
 * @property bool $is_enabled 是否启用
 * @property int $sort_order 排序
 */
class CodingAvailablePeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'coding_account_id',
        'start_time',
        'end_time',
        'weekdays',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * 关联 Coding 账户
     */
    public function codingAccount(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class);
    }

    /**
     * 检查指定时间是否在此时段内
     *
     * @param  Carbon|null  $at  检查的时间点，默认为当前时间
     */
    public function isCurrentlyActive(?Carbon $at = null): bool
    {
        $at = $at ?? now();
        $currentTime = $at->format('H:i:s');
        $currentWeekday = $at->dayOfWeekIso; // 1=Monday, 7=Sunday

        // 检查星期限制
        if ($this->weekdays !== null && ! in_array($currentWeekday, $this->weekdays)) {
            return false;
        }

        $start = $this->start_time;
        $end = $this->end_time;

        // 跨午夜时段：如 22:00-06:00
        if ($start > $end) {
            return $currentTime >= $start || $currentTime < $end;
        }

        // 正常时段：09:00-18:00
        return $currentTime >= $start && $currentTime < $end;
    }

    /**
     * 获取星期选项
     *
     * @return array<int, string>
     */
    public static function getWeekdayOptions(): array
    {
        return [
            1 => '周一',
            2 => '周二',
            3 => '周三',
            4 => '周四',
            5 => '周五',
            6 => '周六',
            7 => '周日',
        ];
    }

    /**
     * 格式化时段显示
     */
    public function getPeriodDisplay(): string
    {
        $display = substr($this->start_time, 0, 5).'-'.substr($this->end_time, 0, 5);
        if ($this->weekdays) {
            $names = array_map(
                fn (int $d) => self::getWeekdayOptions()[$d] ?? (string) $d,
                $this->weekdays
            );
            $display .= ' ('.implode(',', $names).')';
        }

        return $display;
    }
}
