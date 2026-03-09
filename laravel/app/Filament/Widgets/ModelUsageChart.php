<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ModelUsageChart extends ChartWidget
{
    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 3,
    ];

    protected static ?int $sort = 3;

    public ?string $filter = '7d';

    public function getHeading(): ?string
    {
        return '模型使用分布';
    }

    protected function getFilters(): ?array
    {
        return [
            '7d' => '最近 7 天',
            '30d' => '最近 30 天',
            'all' => '全部时间',
        ];
    }

    protected function getData(): array
    {
        $query = AuditLog::query()
            ->select('model', DB::raw('count(*) as count'))
            ->groupBy('model')
            ->orderByDesc('count')
            ->limit(10);

        if ($this->filter === '7d') {
            $query->where('created_at', '>=', now()->subDays(7));
        } elseif ($this->filter === '30d') {
            $query->where('created_at', '>=', now()->subDays(30));
        }

        $data = $query->get();

        $colors = [
            'rgb(251, 191, 36)',
            'rgb(34, 197, 94)',
            'rgb(59, 130, 246)',
            'rgb(168, 85, 247)',
            'rgb(236, 72, 153)',
            'rgb(249, 115, 22)',
            'rgb(20, 184, 166)',
            'rgb(99, 102, 241)',
            'rgb(234, 179, 8)',
            'rgb(107, 114, 128)',
        ];

        return [
            'datasets' => [
                [
                    'label' => '请求数',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $data->count()),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $data->pluck('model')->map(function ($model) {
                return $model ?? '未知模型';
            })->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                ],
            ],
            'cutout' => '60%',
        ];
    }
}
