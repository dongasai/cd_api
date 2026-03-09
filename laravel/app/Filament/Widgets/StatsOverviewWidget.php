<?php

namespace App\Filament\Widgets;

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Channel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        $todayStats = $this->getPeriodStats($today, now());
        $yesterdayStats = $this->getPeriodStats($yesterday, $today);

        $totalRequests = AuditLog::count();
        $successRate = $this->calculateSuccessRate();

        $activeApiKeys = ApiKey::where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        $activeChannels = Channel::where('status', 'active')->count();

        return [
            Stat::make('今日请求数', number_format($todayStats['requests']))
                ->description($this->getTrendDescription($todayStats['requests'], $yesterdayStats['requests']))
                ->descriptionIcon($this->getTrendIcon($todayStats['requests'], $yesterdayStats['requests']))
                ->color($this->getTrendColor($todayStats['requests'], $yesterdayStats['requests']))
                ->chart($this->getRequestTrendChart()),

            Stat::make('今日 Token', $this->formatTokens($todayStats['tokens']))
                ->description($this->getTrendDescription($todayStats['tokens'], $yesterdayStats['tokens']))
                ->descriptionIcon($this->getTrendIcon($todayStats['tokens'], $yesterdayStats['tokens']))
                ->color($this->getTrendColor($todayStats['tokens'], $yesterdayStats['tokens'])),

            Stat::make('今日费用', '$'.number_format($todayStats['cost'], 4))
                ->description($this->getTrendDescription($todayStats['cost'], $yesterdayStats['cost'], true))
                ->descriptionIcon($this->getTrendIcon($todayStats['cost'], $yesterdayStats['cost']))
                ->color($this->getTrendColor($todayStats['cost'], $yesterdayStats['cost'])),

            Stat::make('成功率', number_format($successRate, 1).'%')
                ->description('总请求: '.number_format($totalRequests))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger')),

            Stat::make('活跃 API Key', $activeApiKeys)
                ->description('总 API Key: '.ApiKey::withTrashed()->count())
                ->descriptionIcon('heroicon-m-key')
                ->color('info'),

            Stat::make('活跃渠道', $activeChannels)
                ->description('总渠道: '.Channel::count())
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),
        ];
    }

    protected function getPeriodStats($start, $end): array
    {
        return [
            'requests' => AuditLog::whereBetween('created_at', [$start, $end])->count(),
            'tokens' => AuditLog::whereBetween('created_at', [$start, $end])->sum('total_tokens'),
            'cost' => AuditLog::whereBetween('created_at', [$start, $end])->sum('cost'),
        ];
    }

    protected function calculateSuccessRate(): float
    {
        $total = AuditLog::count();
        if ($total === 0) {
            return 100.0;
        }

        $success = AuditLog::where('status_code', 200)->count();

        return ($success / $total) * 100;
    }

    protected function getTrendDescription($current, $previous, $isCost = false): string
    {
        if ($previous == 0) {
            return '无昨日数据';
        }

        $change = (($current - $previous) / $previous) * 100;
        $direction = $change >= 0 ? '增加' : '减少';

        return $direction.' '.abs(number_format($change, 1)).'%';
    }

    protected function getTrendIcon($current, $previous): string
    {
        if ($previous == 0 || $current == $previous) {
            return 'heroicon-m-minus';
        }

        return $current > $previous ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
    }

    protected function getTrendColor($current, $previous): string
    {
        if ($previous == 0 || $current == $previous) {
            return 'gray';
        }

        return $current > $previous ? 'success' : 'danger';
    }

    protected function formatTokens($tokens): string
    {
        if ($tokens >= 1000000) {
            return number_format($tokens / 1000000, 2).'M';
        }
        if ($tokens >= 1000) {
            return number_format($tokens / 1000, 2).'K';
        }

        return number_format($tokens);
    }

    protected function getRequestTrendChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $count = AuditLog::whereDate('created_at', $date)->count();
            $data[] = $count;
        }

        return $data;
    }
}
