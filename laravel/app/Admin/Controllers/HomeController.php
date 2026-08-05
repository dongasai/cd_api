<?php

namespace App\Admin\Controllers;

use App\Admin\Widgets\Metrics\ApiKeyStatsRound;
use App\Admin\Widgets\Metrics\ChannelDistributionDonut;
use App\Admin\Widgets\Metrics\ChannelStatsRound;
use App\Admin\Widgets\Metrics\PendingMigrationsCard;
use App\Admin\Widgets\Metrics\TodayCostLine;
use App\Admin\Widgets\Metrics\TodayRequestsLine;
use App\Models\AuditLog;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Widgets\Table;

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
                        $row->column(3, new ChannelStatsRound);
                        $row->column(3, new ApiKeyStatsRound);
                        $row->column(3, new TodayRequestsLine);
                        $row->column(3, new TodayCostLine);
                    });
                });

                // 第二行：迁移提醒（仅有待执行时显示）
                $pendingMigrations = new PendingMigrationsCard;
                if ($pendingMigrations->hasPending()) {
                    $row->column(12, $pendingMigrations);
                }

                // 第三行：最近审计日志 + 渠道请求分布
                $row->column(8, $this->buildRecentAuditLogsCard());
                $row->column(4, new ChannelDistributionDonut);
            });
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
}
