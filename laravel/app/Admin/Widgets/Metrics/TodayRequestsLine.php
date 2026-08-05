<?php

namespace App\Admin\Widgets\Metrics;

use App\Models\AuditLog;
use Dcat\Admin\Widgets\Metrics\Line;
use Illuminate\Support\Carbon;

/**
 * 今日请求数折线图卡片
 */
class TodayRequestsLine extends Line
{
    protected function init()
    {
        parent::init();

        $this->title('今日请求');
    }

    public function render()
    {
        $this->fill();

        return parent::render();
    }

    public function fill()
    {
        $today = Carbon::today();
        $todayCount = AuditLog::whereDate('created_at', $today)->count();

        $yesterday = Carbon::yesterday();
        $yesterdayCount = AuditLog::whereDate('created_at', $yesterday)->count();

        $growthRate = $yesterdayCount > 0
            ? round(($todayCount - $yesterdayCount) / $yesterdayCount * 100, 1)
            : ($todayCount > 0 ? 100 : 0);

        $growthSign = $growthRate >= 0 ? '+' : '';

        // 近7天趋势（单次分组查询）
        $data = AuditLog::whereDate('created_at', '>=', Carbon::today()->subDays(6))
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->pluck('count', 'date')
            ->toArray();

        $sevenDaysData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->format('Y-m-d');
            $sevenDaysData[] = $data[$date] ?? 0;
        }

        $this->withContent(number_format($todayCount), $growthSign.$growthRate.'%');
        $this->withChart($sevenDaysData);
    }

    public function withChart(array $data)
    {
        return $this->chart([
            'series' => [
                [
                    'name' => $this->title,
                    'data' => $data,
                ],
            ],
        ]);
    }

    public function withContent($content, $sub)
    {
        return $this->content(
            <<<HTML
<div class="d-flex justify-content-between align-items-center mt-1" style="margin-bottom: 2px">
    <h2 class="ml-1 font-lg-1">{$content}</h2>
    <span class="mb-0 mr-1 text-80">{$sub}</span>
</div>
HTML
        );
    }
}
