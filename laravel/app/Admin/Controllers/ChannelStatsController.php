<?php

namespace App\Admin\Controllers;

use App\Models\AuditLog;
use App\Models\Channel;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\ApexCharts\Chart;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 渠道统计控制器
 */
class ChannelStatsController
{
    /**
     * 渠道统计主页面
     */
    public function index(Content $content, Request $request)
    {
        // 解析筛选参数
        $channelId = $request->get('channel_id');
        $dateRange = $request->get('date_range', '7d');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // 计算日期范围
        [$start, $end] = $this->calculateDateRange($dateRange, $startDate, $endDate);

        return $content
            ->header('渠道统计')
            ->description('渠道使用情况深度分析')
            ->body(function (Row $row) use ($channelId, $start, $end) {
                // 第一行：渠道选择器 + 时间范围选择器
                $row->column(12, function ($column) use ($channelId) {
                    $column->row($this->buildChannelSelector($channelId));
                });

                $row->column(12, function ($column) use ($channelId, $start, $end) {
                    // 第二行：基础统计卡片
                    $column->row(function ($row) use ($channelId, $start, $end) {
                        $row->column(3, $this->buildTotalRequestsCard($channelId, $start, $end));
                        $row->column(3, $this->buildTotalTokensCard($channelId, $start, $end));
                        $row->column(3, $this->buildTotalCostCard($channelId, $start, $end));
                        $row->column(3, $this->buildSuccessRateCard($channelId, $start, $end));
                    });

                    // 第三行：性能指标卡片
                    $column->row(function ($row) use ($channelId, $start, $end) {
                        $row->column(4, $this->buildAvgLatencyCard($channelId, $start, $end));
                        $row->column(4, $this->buildFirstTokenLatencyCard($channelId, $start, $end));
                        $row->column(4, $this->buildSuccessRateTrendCard($channelId, $start, $end));
                    });

                    // 第四行：请求/Token趋势图
                    $column->row($this->buildTrendChart($channelId, $start, $end));

                    // 第五行：客户端分布
                    $column->row(function ($row) use ($channelId, $start, $end) {
                        $row->column(6, $this->buildClientDistributionChart($channelId, $start, $end));
                        $row->column(6, $this->buildTopUserAgentsTable($channelId, $start, $end));
                    });
                });
            });
    }

    /**
     * 构建渠道选择器
     */
    protected function buildChannelSelector(?int $selectedChannelId)
    {
        // 获取所有渠道
        $channels = Channel::orderBy('name')->pluck('name', 'id')->toArray();
        $options = ['' => '所有渠道'] + $channels;

        $optionsHtml = '';
        foreach ($options as $value => $label) {
            $selected = ($value == $selectedChannelId) ? 'selected' : '';
            $valueAttr = $value === '' ? '' : "value=\"{$value}\"";
            $optionsHtml .= "<option {$valueAttr} {$selected}>{$label}</option>";
        }

        $html = <<<HTML
<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="form-inline">
            <div class="form-group mr-3">
                <label class="mr-2">选择渠道：</label>
                <select name="channel_id" class="form-control" onchange="this.form.submit()">
                    {$optionsHtml}
                </select>
            </div>
            <div class="form-group">
                <label class="mr-2">时间范围：</label>
                <div class="btn-group" role="group">
                    <a href="?channel_id={$selectedChannelId}&date_range=today" class="btn btn-outline-primary">今天</a>
                    <a href="?channel_id={$selectedChannelId}&date_range=7d" class="btn btn-outline-primary">7天</a>
                    <a href="?channel_id={$selectedChannelId}&date_range=30d" class="btn btn-outline-primary">30天</a>
                </div>
            </div>
        </form>
    </div>
</div>
HTML;

        return $html;
    }

    /**
     * 构建总请求数卡片
     */
    protected function buildTotalRequestsCard(?int $channelId, Carbon $start, Carbon $end): Card
    {
        // 查询指定时间范围的请求数
        $query = AuditLog::whereBetween('created_at', [$start, $end]);
        if ($channelId) {
            $query->where('channel_id', $channelId);
        }
        $totalRequests = $query->count();

        // 计算上一周期的数据用于环比
        $previousStart = (clone $start)->subDays($end->diffInDays($start) + 1);
        $previousEnd = (clone $start)->subSecond();

        $previousQuery = AuditLog::whereBetween('created_at', [$previousStart, $previousEnd]);
        if ($channelId) {
            $previousQuery->where('channel_id', $channelId);
        }
        $previousRequests = $previousQuery->count();

        // 计算环比增长率
        $growthRate = $previousRequests > 0
            ? round(($totalRequests - $previousRequests) / $previousRequests * 100, 1)
            : ($totalRequests > 0 ? 100 : 0);

        $growthClass = $growthRate >= 0 ? 'text-success' : 'text-danger';
        $growthIcon = $growthRate >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$totalRequests}</h2>
        <p class="mb-0 text-muted">总请求数</p>
    </div>
    <div class="text-right {$growthClass}">
        <i class="fa {$growthIcon}"></i> {$growthRate}%
        <small class="d-block text-muted">环比</small>
    </div>
</div>
HTML;

        return Card::make('总请求数', $content)
            ->icon('fa fa-paper-plane')
            ->style('primary');
    }

    /**
     * 构建总Token卡片
     */
    protected function buildTotalTokensCard(?int $channelId, Carbon $start, Carbon $end): Card
    {
        // 查询指定时间范围的Token数
        $query = AuditLog::whereBetween('created_at', [$start, $end]);
        if ($channelId) {
            $query->where('channel_id', $channelId);
        }
        $totalTokens = $query->sum('total_tokens');

        // 计算上一周期的数据
        $previousStart = (clone $start)->subDays($end->diffInDays($start) + 1);
        $previousEnd = (clone $start)->subSecond();

        $previousQuery = AuditLog::whereBetween('created_at', [$previousStart, $previousEnd]);
        if ($channelId) {
            $previousQuery->where('channel_id', $channelId);
        }
        $previousTokens = $previousQuery->sum('total_tokens');

        // 计算环比增长率
        $growthRate = $previousTokens > 0
            ? round(($totalTokens - $previousTokens) / $previousTokens * 100, 1)
            : ($totalTokens > 0 ? 100 : 0);

        $growthClass = $growthRate >= 0 ? 'text-success' : 'text-danger';
        $growthIcon = $growthRate >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        $formattedTokens = number_format($totalTokens);

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$formattedTokens}</h2>
        <p class="mb-0 text-muted">总Token</p>
    </div>
    <div class="text-right {$growthClass}">
        <i class="fa {$growthIcon}"></i> {$growthRate}%
        <small class="d-block text-muted">环比</small>
    </div>
</div>
HTML;

        return Card::make('总Token', $content)
            ->icon('fa fa-coins')
            ->style('info');
    }

    /**
     * 构建总费用卡片
     */
    protected function buildTotalCostCard(?int $channelId, Carbon $start, Carbon $end): Card
    {
        // 查询指定时间范围的费用
        $query = AuditLog::whereBetween('created_at', [$start, $end]);
        if ($channelId) {
            $query->where('channel_id', $channelId);
        }
        $totalCost = $query->sum('cost');

        // 计算上一周期的数据
        $previousStart = (clone $start)->subDays($end->diffInDays($start) + 1);
        $previousEnd = (clone $start)->subSecond();

        $previousQuery = AuditLog::whereBetween('created_at', [$previousStart, $previousEnd]);
        if ($channelId) {
            $previousQuery->where('channel_id', $channelId);
        }
        $previousCost = $previousQuery->sum('cost');

        // 计算环比增长率
        $growthRate = $previousCost > 0
            ? round(($totalCost - $previousCost) / $previousCost * 100, 1)
            : ($totalCost > 0 ? 100 : 0);

        $growthClass = $growthRate >= 0 ? 'text-danger' : 'text-success';
        $growthIcon = $growthRate >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        $formattedCost = '$'.number_format($totalCost, 4);

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$formattedCost}</h2>
        <p class="mb-0 text-muted">总费用</p>
    </div>
    <div class="text-right {$growthClass}">
        <i class="fa {$growthIcon}"></i> {$growthRate}%
        <small class="d-block text-muted">环比</small>
    </div>
</div>
HTML;

        return Card::make('总费用', $content)
            ->icon('fa fa-dollar-sign')
            ->style('warning');
    }

    /**
     * 构建成功率卡片
     */
    protected function buildSuccessRateCard(?int $channelId, Carbon $start, Carbon $end): Card
    {
        // 查询总请求数和成功请求数
        $query = AuditLog::whereBetween('created_at', [$start, $end]);
        if ($channelId) {
            $query->where('channel_id', $channelId);
        }
        $totalRequests = $query->count();

        $successQuery = AuditLog::whereBetween('created_at', [$start, $end])
            ->whereBetween('status_code', [200, 299]);
        if ($channelId) {
            $successQuery->where('channel_id', $channelId);
        }
        $successRequests = $successQuery->count();

        // 计算成功率
        $successRate = $totalRequests > 0 ? round($successRequests / $totalRequests * 100, 2) : 0;

        // 计算上一周期的数据
        $previousStart = (clone $start)->subDays($end->diffInDays($start) + 1);
        $previousEnd = (clone $start)->subSecond();

        $previousQuery = AuditLog::whereBetween('created_at', [$previousStart, $previousEnd]);
        if ($channelId) {
            $previousQuery->where('channel_id', $channelId);
        }
        $previousTotal = $previousQuery->count();

        $previousSuccessQuery = AuditLog::whereBetween('created_at', [$previousStart, $previousEnd])
            ->whereBetween('status_code', [200, 299]);
        if ($channelId) {
            $previousSuccessQuery->where('channel_id', $channelId);
        }
        $previousSuccess = $previousSuccessQuery->count();
        $previousRate = $previousTotal > 0 ? round($previousSuccess / $previousTotal * 100, 2) : 0;

        // 计算环比增长
        $growthRate = $previousRate > 0 ? round($successRate - $previousRate, 2) : 0;
        $growthClass = $growthRate >= 0 ? 'text-success' : 'text-danger';
        $growthIcon = $growthRate >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$successRate}%</h2>
        <p class="mb-0 text-muted">成功率</p>
    </div>
    <div class="text-right {$growthClass}">
        <i class="fa {$growthIcon}"></i> {$growthRate}%
        <small class="d-block text-muted">环比</small>
    </div>
</div>
HTML;

        return Card::make('成功率', $content)
            ->icon('fa fa-check-circle')
            ->style('success');
    }

    /**
     * 构建平均延迟卡片
     */
    protected function buildAvgLatencyCard(?int $channelId, Carbon $start, Carbon $end): Card
    {
        // 查询平均延迟
        $query = AuditLog::whereBetween('created_at', [$start, $end])
            ->where('latency_ms', '>', 0);
        if ($channelId) {
            $query->where('channel_id', $channelId);
        }
        $avgLatency = $query->avg('latency_ms') ?? 0;

        // 计算上一周期的数据
        $previousStart = (clone $start)->subDays($end->diffInDays($start) + 1);
        $previousEnd = (clone $start)->subSecond();

        $previousQuery = AuditLog::whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('latency_ms', '>', 0);
        if ($channelId) {
            $previousQuery->where('channel_id', $channelId);
        }
        $previousAvgLatency = $previousQuery->avg('latency_ms') ?? 0;

        // 计算环比增长（延迟下降是好现象，所以颜色反转）
        $growthRate = $previousAvgLatency > 0
            ? round(($avgLatency - $previousAvgLatency) / $previousAvgLatency * 100, 1)
            : 0;
        $growthClass = $growthRate <= 0 ? 'text-success' : 'text-danger';
        $growthIcon = $growthRate <= 0 ? 'fa-arrow-down' : 'fa-arrow-up';
        $formattedLatency = round($avgLatency, 0).'ms';
        $absGrowthRate = abs($growthRate);

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$formattedLatency}</h2>
        <p class="mb-0 text-muted">平均延迟</p>
    </div>
    <div class="text-right {$growthClass}">
        <i class="fa {$growthIcon}"></i> {$absGrowthRate}%
        <small class="d-block text-muted">环比</small>
    </div>
</div>
HTML;

        return Card::make('平均延迟', $content)
            ->icon('fa fa-clock')
            ->style('secondary');
    }

    /**
     * 构建首Token延迟卡片
     */
    protected function buildFirstTokenLatencyCard(?int $channelId, Carbon $start, Carbon $end): Card
    {
        // 查询首Token平均延迟
        $query = AuditLog::whereBetween('created_at', [$start, $end])
            ->where('first_token_ms', '>', 0);
        if ($channelId) {
            $query->where('channel_id', $channelId);
        }
        $avgFirstToken = $query->avg('first_token_ms') ?? 0;

        // 计算上一周期的数据
        $previousStart = (clone $start)->subDays($end->diffInDays($start) + 1);
        $previousEnd = (clone $start)->subSecond();

        $previousQuery = AuditLog::whereBetween('created_at', [$previousStart, $previousEnd])
            ->where('first_token_ms', '>', 0);
        if ($channelId) {
            $previousQuery->where('channel_id', $channelId);
        }
        $previousAvgFirstToken = $previousQuery->avg('first_token_ms') ?? 0;

        // 计算环比增长
        $growthRate = $previousAvgFirstToken > 0
            ? round(($avgFirstToken - $previousAvgFirstToken) / $previousAvgFirstToken * 100, 1)
            : 0;
        $growthClass = $growthRate <= 0 ? 'text-success' : 'text-danger';
        $growthIcon = $growthRate <= 0 ? 'fa-arrow-down' : 'fa-arrow-up';
        $formattedLatency = round($avgFirstToken, 0).'ms';
        $absGrowthRate = abs($growthRate);

        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$formattedLatency}</h2>
        <p class="mb-0 text-muted">首Token延迟</p>
    </div>
    <div class="text-right {$growthClass}">
        <i class="fa {$growthIcon}"></i> {$absGrowthRate}%
        <small class="d-block text-muted">环比</small>
    </div>
</div>
HTML;

        return Card::make('首Token延迟', $content)
            ->icon('fa fa-bolt')
            ->style('info');
    }

    /**
     * 构建成功率趋势卡片
     */
    protected function buildSuccessRateTrendCard(?int $channelId, Carbon $start, Carbon $end): Card
    {
        // 查询每日成功率数据
        $query = AuditLog::whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as success')
            )
            ->groupBy('date')
            ->orderBy('date');

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        $dailyStats = $query->get();

        // 填充缺失日期
        $dateRange = [];
        $current = clone $start;
        while ($current <= $end) {
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
        $avgRate = count($rates) > 0 ? round(array_sum($rates) / count($rates), 2) : 0;

        // 创建趋势图
        $chart = Chart::make()
            ->series([[
                'name' => '成功率',
                'data' => $rates,
                'type' => 'line',
            ]])
            ->labels($dates)
            ->chart([
                'height' => 120,
                'toolbar' => ['show' => false],
                'sparkline' => ['enabled' => true],
            ])
            ->yaxis([[
                'min' => 0,
                'max' => 100,
                'labels' => [
                    'formatter' => 'function(val) { return val.toFixed(0) + "%"; }',
                ],
            ]])
            ->stroke([
                'curve' => 'smooth',
                'width' => 2,
            ])
            ->colors(['#28a745']);

        $content = $chart->render();
        $content .= "<div class='text-center mt-2'><small class='text-muted'>平均成功率: {$avgRate}%</small></div>";

        return Card::make('成功率趋势', $content)
            ->icon('fa fa-chart-line');
    }

    /**
     * 构建请求/Token趋势图
     */
    protected function buildTrendChart(?int $channelId, Carbon $start, Carbon $end)
    {
        // 查询每日统计数据
        $query = AuditLog::whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_tokens) as token_count')
            )
            ->groupBy('date')
            ->orderBy('date');

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        $dailyStats = $query->get()->keyBy('date');

        // 填充缺失日期
        $dates = [];
        $requestCounts = [];
        $tokenCounts = [];
        $current = clone $start;

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $dates[] = $current->format('m-d');
            $requestCounts[] = $dailyStats->get($dateStr)?->request_count ?? 0;
            $tokenCounts[] = $dailyStats->get($dateStr)?->token_count ?? 0;
            $current->addDay();
        }

        // 创建双Y轴折线图
        $chart = Chart::make()
            ->series([
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
            ])
            ->labels($dates)
            ->chart([
                'type' => 'line',
                'height' => 350,
                'toolbar' => [
                    'show' => true,
                ],
                'zoom' => [
                    'enabled' => true,
                ],
            ])
            ->yaxis([
                [
                    'title' => ['text' => '请求数'],
                    'labels' => [
                        'formatter' => 'function(val) { return val.toLocaleString(); }',
                    ],
                ],
                [
                    'title' => ['text' => 'Token数'],
                    'opposite' => true,
                    'labels' => [
                        'formatter' => 'function(val) { return val.toLocaleString(); }',
                    ],
                ],
            ])
            ->xaxis([
                'type' => 'category',
                'labels' => [
                    'rotate' => -45,
                    'rotateAlways' => true,
                ],
            ])
            ->stroke([
                'curve' => 'smooth',
                'width' => 2,
            ])
            ->dataLabels([
                'enabled' => false,
            ])
            ->legend([
                'position' => 'top',
            ])
            ->colors(['#5c6bc0', '#42a5f5']);

        return Card::make('请求/Token趋势', $chart);
    }

    /**
     * 构建客户端分布饼图
     */
    protected function buildClientDistributionChart(?int $channelId, Carbon $start, Carbon $end): Card
    {
        // 按User-Agent前缀分组统计
        $query = AuditLog::whereBetween('created_at', [$start, $end])
            ->whereNotNull('user_agent')
            ->select(
                DB::raw('SUBSTRING_INDEX(user_agent, "/", 1) as client'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('client')
            ->orderBy('count', 'desc')
            ->limit(10);

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        $clientStats = $query->get();

        // 构建图表数据
        $labels = $clientStats->pluck('client')->toArray();
        $series = $clientStats->pluck('count')->toArray();

        // 创建饼图
        $chart = Chart::make()
            ->series([[
                'name' => '请求数',
                'data' => $series,
            ]])
            ->labels($labels)
            ->chart([
                'type' => 'pie',
                'height' => 280,
                'toolbar' => [
                    'show' => false,
                ],
            ])
            ->dataLabels([
                'enabled' => true,
                'formatter' => 'function(val) { return val.toFixed(1) + "%"; }',
            ])
            ->legend([
                'position' => 'bottom',
                'fontSize' => '12px',
            ])
            ->colors(['#5c6bc0', '#42a5f5', '#26a69a', '#66bb6a', '#ffa726', '#ef5350', '#ab47bc', '#ec407a', '#ff7043', '#d4e157']);

        return Card::make('客户端分布', $chart);
    }

    /**
     * 构建Top User-Agent表格
     */
    protected function buildTopUserAgentsTable(?int $channelId, Carbon $start, Carbon $end): Card
    {
        // 查询Top 10 User-Agent
        $query = AuditLog::whereBetween('created_at', [$start, $end])
            ->whereNotNull('user_agent')
            ->select(
                'user_agent',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(cost) as total_cost')
            )
            ->groupBy('user_agent')
            ->orderBy('request_count', 'desc')
            ->limit(10);

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }

        $topUserAgents = $query->get();

        // 构建表格数据
        $headers = ['User-Agent', '请求数', 'Token', '费用'];
        $rows = [];

        foreach ($topUserAgents as $item) {
            $rows[] = [
                $item->user_agent,
                number_format($item->request_count),
                number_format($item->total_tokens),
                '$'.number_format($item->total_cost, 4),
            ];
        }

        $table = Table::make($headers, $rows)
            ->class('table table-striped table-hover table-sm');

        return Card::make('Top 10 User-Agent', $table);
    }

    /**
     * 计算日期范围
     */
    protected function calculateDateRange(string $dateRange, ?string $startDate, ?string $endDate): array
    {
        switch ($dateRange) {
            case 'today':
                return [Carbon::today(), Carbon::now()];

            case '7d':
                return [Carbon::now()->subDays(7)->startOfDay(), Carbon::now()];

            case '30d':
                return [Carbon::now()->subDays(30)->startOfDay(), Carbon::now()];

            case 'custom':
                if ($startDate && $endDate) {
                    return [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()];
                }

                return [Carbon::now()->subDays(7)->startOfDay(), Carbon::now()];

            default:
                return [Carbon::now()->subDays(7)->startOfDay(), Carbon::now()];
        }
    }
}
