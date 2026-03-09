<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Widgets\ChartWidget;

class TokenUsageChart extends ChartWidget
{
    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 3,
    ];

    protected static ?int $sort = 4;

    public ?string $filter = '7d';

    public function getHeading(): ?string
    {
        return 'Token 使用趋势';
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
                    'label' => '输入 Token',
                    'data' => $data['prompt'],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'stack' => 'tokens',
                ],
                [
                    'label' => '输出 Token',
                    'data' => $data['completion'],
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'stack' => 'tokens',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
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
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getChartData(int $days): array
    {
        $labels = [];
        $prompt = [];
        $completion = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $labels[] = $date->format('m-d');

            $dayPrompt = AuditLog::whereDate('created_at', $date)->sum('prompt_tokens');
            $dayCompletion = AuditLog::whereDate('created_at', $date)->sum('completion_tokens');

            $prompt[] = $dayPrompt;
            $completion[] = $dayCompletion;
        }

        return [
            'labels' => $labels,
            'prompt' => $prompt,
            'completion' => $completion,
        ];
    }
}
