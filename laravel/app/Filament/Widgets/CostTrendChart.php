<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Widgets\ChartWidget;

class CostTrendChart extends ChartWidget
{
    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 3,
    ];

    protected static ?int $sort = 7;

    public ?string $filter = '7d';

    public function getHeading(): ?string
    {
        return '费用趋势';
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
                    'label' => '费用 ($)',
                    'data' => $data['cost'],
                    'borderColor' => 'rgb(168, 85, 247)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
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
        $cost = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $labels[] = $date->format('m-d');

            $dayCost = AuditLog::whereDate('created_at', $date)->sum('cost');
            $cost[] = round($dayCost, 6);
        }

        return [
            'labels' => $labels,
            'cost' => $cost,
        ];
    }
}
