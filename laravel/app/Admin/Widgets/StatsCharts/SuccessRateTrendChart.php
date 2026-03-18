<?php

namespace App\Admin\Widgets\StatsCharts;

use App\Models\AuditLog;
use Dcat\Admin\Widgets\ApexCharts\Chart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 成功率趋势图组件
 */
class SuccessRateTrendChart extends Chart
{
    protected ?int $channelId = null;

    protected Carbon $start;

    protected Carbon $end;

    public function __construct($containerSelector = null, $options = [])
    {
        parent::__construct($containerSelector, $options);
    }

    /**
     * 设置查询参数
     */
    public function setParams(?int $channelId, Carbon $start, Carbon $end): self
    {
        $this->channelId = $channelId;
        $this->start = $start;
        $this->end = $end;

        return $this;
    }

    /**
     * 初始化图表配置
     */
    protected function setUpOptions()
    {
        $this->options([
            'chart' => [
                'height' => 120,
                'toolbar' => ['show' => false],
                'sparkline' => ['enabled' => true],
            ],
            'yaxis' => [
                [
                    'min' => 0,
                    'max' => 100,
                ],
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2,
            ],
            'colors' => ['#28a745'],
        ]);
    }

    /**
     * 处理图表数据
     */
    protected function buildData()
    {
        // 查询每日成功率数据
        $query = AuditLog::whereBetween('created_at', [$this->start, $this->end])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as success')
            )
            ->groupBy('date')
            ->orderBy('date');

        if ($this->channelId) {
            $query->where('channel_id', $this->channelId);
        }

        $dailyStats = $query->get();

        // 填充缺失日期
        $dateRange = [];
        $current = clone $this->start;
        while ($current <= $this->end) {
            $dateRange[$current->format('Y-m-d')] = [
                'date' => $current->format('Y-m-d'),
                'total' => 0,
                'success' => 0,
                'rate' => 0,
            ];
            $current->addDay();
        }

        foreach ($dailyStats as $stat) {
            $dateRange[$stat->date] = [
                'date' => $stat->date,
                'total' => $stat->total,
                'success' => $stat->success,
                'rate' => $stat->total > 0 ? round($stat->success / $stat->total * 100, 2) : 0,
            ];
        }

        $dates = array_keys($dateRange);
        $rates = array_column($dateRange, 'rate');

        $this->withData($rates);
        $this->withLabels($dates);
    }

    /**
     * 设置图表数据
     */
    public function withData(array $data)
    {
        return $this->option('series', [[
            'name' => '成功率',
            'data' => $data,
            'type' => 'line',
        ]]);
    }

    /**
     * 设置图表标签
     */
    public function withLabels(array $labels)
    {
        return $this->option('xaxis.categories', $labels);
    }

    /**
     * 渲染图表
     */
    public function render()
    {
        $this->setUpOptions();
        $this->buildData();

        return parent::render();
    }
}
