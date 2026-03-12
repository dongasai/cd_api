<?php

namespace App\Admin\Controllers;

use App\Models\ApiKey;
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

class HomeController
{
    /**
     * 后台首页仪表盘
     */
    public function index(Content $content)
    {
        return $content
            ->header('仪表盘')
            ->description('CdApi 管理后台')
            ->body(function (Row $row) {
                // 第一行：统计卡片
                $row->column(12, function ($column) {
                    $column->row(function ($row) {
                        $row->column(3, $this->buildChannelStatsCard());
                        $row->column(3, $this->buildApiKeyStatsCard());
                        $row->column(3, $this->buildTodayRequestsCard());
                        $row->column(3, $this->buildTodayCostCard());
                    });
                });

                // 第二行：最近审计日志 + 渠道请求分布图表
                $row->column(8, $this->buildRecentAuditLogsCard());
                $row->column(4, $this->buildChannelDistributionCard());
            });
    }

    /**
     * 构建渠道统计卡片
     */
    protected function buildChannelStatsCard(): Card
    {
        // 统计渠道总数和状态
        $totalChannels = Channel::count();
        $activeChannels = Channel::where('status', 'active')->count();
        $disabledChannels = Channel::where('status', 'disabled')->count();

        // 构建卡片内容
        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$totalChannels}</h2>
        <p class="mb-0 text-muted">渠道总数</p>
    </div>
    <div class="text-right">
        <span class="badge badge-success">{$activeChannels} 活跃</span>
        <span class="badge badge-secondary">{$disabledChannels} 禁用</span>
    </div>
</div>
HTML;

        return Card::make('渠道统计', $content)
            ->icon('fa fa-server')
            ->style('primary');
    }

    /**
     * 构建API密钥统计卡片
     */
    protected function buildApiKeyStatsCard(): Card
    {
        // 统计API密钥总数和状态
        $totalKeys = ApiKey::count();
        $activeKeys = ApiKey::where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
        $expiredKeys = ApiKey::where('expires_at', '<', now())->count();

        // 构建卡片内容
        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$totalKeys}</h2>
        <p class="mb-0 text-muted">API密钥总数</p>
    </div>
    <div class="text-right">
        <span class="badge badge-success">{$activeKeys} 活跃</span>
        <span class="badge badge-danger">{$expiredKeys} 过期</span>
    </div>
</div>
HTML;

        return Card::make('API密钥统计', $content)
            ->icon('fa fa-key')
            ->style('info');
    }

    /**
     * 构建今日请求数卡片
     */
    protected function buildTodayRequestsCard(): Card
    {
        // 统计今日请求数
        $today = Carbon::today();
        $todayRequests = AuditLog::whereDate('created_at', $today)->count();

        // 统计昨日请求数用于对比
        $yesterday = Carbon::yesterday();
        $yesterdayRequests = AuditLog::whereDate('created_at', $yesterday)->count();

        // 计算增长率
        $growthRate = $yesterdayRequests > 0
            ? round(($todayRequests - $yesterdayRequests) / $yesterdayRequests * 100, 1)
            : ($todayRequests > 0 ? 100 : 0);

        $growthClass = $growthRate >= 0 ? 'text-success' : 'text-danger';
        $growthIcon = $growthRate >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';

        // 构建卡片内容
        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$todayRequests}</h2>
        <p class="mb-0 text-muted">今日请求数</p>
    </div>
    <div class="text-right {$growthClass}">
        <i class="fa {$growthIcon}"></i> {$growthRate}%
        <small class="d-block text-muted">较昨日</small>
    </div>
</div>
HTML;

        return Card::make('今日请求', $content)
            ->icon('fa fa-paper-plane')
            ->style('success');
    }

    /**
     * 构建今日费用卡片
     */
    protected function buildTodayCostCard(): Card
    {
        // 统计今日费用
        $today = Carbon::today();
        $todayCost = AuditLog::whereDate('created_at', $today)
            ->sum('cost');

        // 统计昨日费用用于对比
        $yesterday = Carbon::yesterday();
        $yesterdayCost = AuditLog::whereDate('created_at', $yesterday)
            ->sum('cost');

        // 计算增长率
        $growthRate = $yesterdayCost > 0
            ? round(($todayCost - $yesterdayCost) / $yesterdayCost * 100, 1)
            : ($todayCost > 0 ? 100 : 0);

        $growthClass = $growthRate >= 0 ? 'text-danger' : 'text-success';
        $growthIcon = $growthRate >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';

        // 格式化费用显示
        $formattedTodayCost = '$'.number_format($todayCost, 4);

        // 构建卡片内容
        $content = <<<HTML
<div class="d-flex justify-content-between align-items-center">
    <div>
        <h2 class="font-weight-bold">{$formattedTodayCost}</h2>
        <p class="mb-0 text-muted">今日费用</p>
    </div>
    <div class="text-right {$growthClass}">
        <i class="fa {$growthIcon}"></i> {$growthRate}%
        <small class="d-block text-muted">较昨日</small>
    </div>
</div>
HTML;

        return Card::make('今日费用', $content)
            ->icon('fa fa-dollar-sign')
            ->style('warning');
    }

    /**
     * 构建最近审计日志卡片
     */
    protected function buildRecentAuditLogsCard(): Card
    {
        // 获取最近10条审计日志
        $logs = AuditLog::with(['channel'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        // 构建表格数据
        $headers = ['用户', '渠道', '模型', 'Token数', '费用', '状态码', '时间'];
        $rows = [];

        foreach ($logs as $log) {
            $statusBadge = $this->formatStatusCode($log->status_code);
            $formattedCost = $log->cost ? '$'.number_format($log->cost, 6) : '-';
            $formattedTokens = number_format($log->total_tokens);
            $formattedTime = $log->created_at ? $log->created_at->format('m-d H:i:s') : '-';

            $rows[] = [
                $log->username ?: '-',
                $log->channel_name ?: '-',
                $log->model ?: '-',
                $formattedTokens,
                $formattedCost,
                $statusBadge,
                $formattedTime,
            ];
        }

        $table = Table::make($headers, $rows)
            ->class('table table-striped table-hover');

        return Card::make('最近审计日志', $table)
            ->tool('<a href="'.admin_url('audit-logs').'" class="btn btn-primary btn-sm">查看全部</a>');
    }

    /**
     * 构建渠道请求分布卡片
     */
    protected function buildChannelDistributionCard(): Card
    {
        // 获取今日各渠道请求量
        $today = Carbon::today();
        $channelStats = AuditLog::whereDate('created_at', $today)
            ->select('channel_name', DB::raw('count(*) as request_count'))
            ->whereNotNull('channel_name')
            ->groupBy('channel_name')
            ->orderBy('request_count', 'desc')
            ->limit(10)
            ->get();

        // 构建图表数据
        $labels = $channelStats->pluck('channel_name')->toArray();
        $series = $channelStats->pluck('request_count')->toArray();

        // 创建饼图
        $chart = Chart::make()
            ->series([
                [
                    'name' => '请求数',
                    'data' => $series,
                ],
            ])
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

        return Card::make('渠道请求分布', $chart)
            ->tool('<small class="text-muted">今日数据</small>');
    }

    /**
     * 格式化状态码显示
     */
    protected function formatStatusCode(?int $statusCode): string
    {
        if ($statusCode === null) {
            return '<span class="badge badge-secondary">-</span>';
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return "<span class='badge badge-success'>{$statusCode}</span>";
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            return "<span class='badge badge-warning'>{$statusCode}</span>";
        }

        if ($statusCode >= 500) {
            return "<span class='badge badge-danger'>{$statusCode}</span>";
        }

        return "<span class='badge badge-secondary'>{$statusCode}</span>";
    }

    /**
     * 获取仪表盘统计数据API（可选，用于异步刷新）
     */
    public function stats(Request $request)
    {
        $today = Carbon::today();

        return response()->json([
            'channels' => [
                'total' => Channel::count(),
                'active' => Channel::where('status', 'active')->count(),
            ],
            'api_keys' => [
                'total' => ApiKey::count(),
                'active' => ApiKey::where('status', 'active')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->count(),
            ],
            'today_requests' => AuditLog::whereDate('created_at', $today)->count(),
            'today_cost' => AuditLog::whereDate('created_at', $today)->sum('cost'),
        ]);
    }
}
