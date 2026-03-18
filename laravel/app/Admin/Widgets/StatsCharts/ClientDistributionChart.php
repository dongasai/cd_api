<?php

namespace App\Admin\Widgets\StatsCharts;

use App\Models\AuditLog;
use Dcat\Admin\Widgets\ApexCharts\Chart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 客户端分布饼图组件
 */
class ClientDistributionChart extends Chart
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
                'type' => 'pie',
                'height' => 280,
                'toolbar' => [
                    'show' => false,
                ],
            ],
            'legend' => [
                'position' => 'bottom',
                'fontSize' => '12px',
            ],
            'colors' => ['#5c6bc0', '#42a5f5', '#26a69a', '#66bb6a', '#ffa726', '#ef5350', '#ab47bc', '#ec407a', '#ff7043', '#d4e157'],
        ]);
    }

    /**
     * 处理图表数据
     */
    protected function buildData()
    {
        // 按User-Agent前缀分组统计
        $query = AuditLog::whereBetween('created_at', [$this->start, $this->end])
            ->whereNotNull('user_agent')
            ->select(
                DB::raw('SUBSTRING_INDEX(user_agent, "/", 1) as client'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('client')
            ->orderBy('count', 'desc')
            ->limit(10);

        if ($this->channelId) {
            $query->where('channel_id', $this->channelId);
        }

        $clientStats = $query->get();

        // 构建图表数据
        $labels = $clientStats->pluck('client')->toArray();
        $series = array_map('intval', $clientStats->pluck('count')->toArray());

        $this->withData($series);
        $this->withLabels($labels);
    }

    /**
     * 设置图表数据
     */
    public function withData(array $data)
    {
        return $this->option('series', $data);
    }

    /**
     * 设置图表标签
     */
    public function withLabels(array $labels)
    {
        return $this->option('labels', $labels);
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
