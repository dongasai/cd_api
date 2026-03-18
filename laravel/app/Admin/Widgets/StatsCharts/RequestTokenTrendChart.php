<?php

namespace App\Admin\Widgets\StatsCharts;

use App\Models\AuditLog;
use Dcat\Admin\Widgets\ApexCharts\Chart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 请求/Token趋势图组件
 */
class RequestTokenTrendChart extends Chart
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
                'type' => 'line',
                'height' => 350,
                'toolbar' => [
                    'show' => true,
                ],
                'zoom' => [
                    'enabled' => true,
                ],
            ],
            'yaxis' => [
                [
                    'title' => ['text' => '请求数'],
                ],
                [
                    'title' => ['text' => 'Token数'],
                    'opposite' => true,
                ],
            ],
            'xaxis' => [
                'type' => 'category',
                'labels' => [
                    'rotate' => -45,
                    'rotateAlways' => true,
                ],
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2,
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'legend' => [
                'position' => 'top',
            ],
            'colors' => ['#5c6bc0', '#42a5f5'],
        ]);
    }

    /**
     * 处理图表数据
     */
    protected function buildData()
    {
        // 查询每日统计数据
        $query = AuditLog::whereBetween('created_at', [$this->start, $this->end])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_tokens) as token_count')
            )
            ->groupBy('date')
            ->orderBy('date');

        if ($this->channelId) {
            $query->where('channel_id', $this->channelId);
        }

        $dailyStats = $query->get()->keyBy('date');

        // 填充缺失日期
        $dates = [];
        $requestCounts = [];
        $tokenCounts = [];
        $current = clone $this->start;

        while ($current <= $this->end) {
            $dateStr = $current->format('Y-m-d');
            $dates[] = $current->format('m-d');
            $requestCounts[] = $dailyStats->get($dateStr)?->request_count ?? 0;
            $tokenCounts[] = $dailyStats->get($dateStr)?->token_count ?? 0;
            $current->addDay();
        }

        $this->withData($requestCounts, $tokenCounts);
        $this->withLabels($dates);
    }

    /**
     * 设置图表数据
     */
    public function withData(array $requestCounts, array $tokenCounts)
    {
        return $this->option('series', [
            [
                'name' => '请求数',
                'data' => $requestCounts,
                'type' => 'line',
            ],
            [
                'name' => 'Token数',
                'data' => $tokenCounts,
                'type' => 'line',
            ],
        ]);
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
