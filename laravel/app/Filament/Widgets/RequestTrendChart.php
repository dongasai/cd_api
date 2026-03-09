<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Widgets\ChartWidget;

class RequestTrendChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public ?string $filter = '7d';

    public function getHeading(): ?string
    {
        return '请求趋势';
    }

    protected function getFilters(): ?array
    {
        return [
            '7d' => '最近 7 天',
            '30d' => '最近 30 天',
            '90d' => '最近 90 天',
        ];
    }

    protected function getData(): array
    {
        $days = match ($this->filter) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $data = $this->getChartData($days);

        return [
            'datasets' => [
                [
                    'label' => '请求数',
                    'data' => $data['requests'],
                    'borderColor' => 'rgb(251, 191, 36)',
                    'backgroundColor' => 'rgba(251, 191, 36, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => '成功请求',
                    'data' => $data['success'],
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => '失败请求',
                    'data' => $data['failed'],
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getChartData(int $days): array
    {
        $labels = [];
        $requests = [];
        $success = [];
        $failed = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $labels[] = $date->format('m-d');

            $dayRequests = AuditLog::whereDate('created_at', $date)->count();
            $daySuccess = AuditLog::whereDate('created_at', $date)
                ->where('status_code', 200)
                ->count();
            $dayFailed = $dayRequests - $daySuccess;

            $requests[] = $dayRequests;
            $success[] = $daySuccess;
            $failed[] = $dayFailed;
        }

        return [
            'labels' => $labels,
            'requests' => $requests,
            'success' => $success,
            'failed' => $failed,
        ];
    }
}
