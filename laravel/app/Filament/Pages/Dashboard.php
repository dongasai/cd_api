<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ChannelStatusWidget;
use App\Filament\Widgets\CostTrendChart;
use App\Filament\Widgets\ModelUsageChart;
use App\Filament\Widgets\RecentRequestsWidget;
use App\Filament\Widgets\RequestTrendChart;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TokenUsageChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = '数据看板';

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            RequestTrendChart::class,
            ModelUsageChart::class,
            TokenUsageChart::class,
            CostTrendChart::class,
            ChannelStatusWidget::class,
            RecentRequestsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 4,
            '2xl' => 4,
        ];
    }
}
