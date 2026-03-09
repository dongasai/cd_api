<?php

namespace App\Filament\Widgets;

use App\Models\Channel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ChannelStatusWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    public function getHeading(): ?string
    {
        return '渠道状态概览';
    }

    protected function getTableQuery(): Builder
    {
        return Channel::query()
            ->select(['id', 'name', 'provider', 'status', 'health_status', 'success_rate', 'avg_latency_ms', 'total_requests'])
            ->orderByDesc('total_requests')
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('渠道名称')
                ->searchable()
                ->limit(20),

            TextColumn::make('provider')
                ->label('提供商')
                ->badge(),

            TextColumn::make('status')
                ->label('状态')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'active' => '活跃',
                    'disabled' => '禁用',
                    'maintenance' => '维护中',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'disabled' => 'danger',
                    'maintenance' => 'warning',
                    default => 'gray',
                }),

            TextColumn::make('health_status')
                ->label('健康状态')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'healthy' => '健康',
                    'unhealthy' => '不健康',
                    'unknown' => '未知',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'healthy' => 'success',
                    'unhealthy' => 'danger',
                    'unknown' => 'gray',
                    default => 'gray',
                }),

            TextColumn::make('success_rate')
                ->label('成功率')
                ->formatStateUsing(fn ($state) => $state ? number_format($state * 100, 1).'%' : '-')
                ->color(fn ($state) => $state >= 0.95 ? 'success' : ($state >= 0.8 ? 'warning' : 'danger')),

            TextColumn::make('avg_latency_ms')
                ->label('平均延迟')
                ->formatStateUsing(fn ($state) => $state ? $state.'ms' : '-'),

            TextColumn::make('total_requests')
                ->label('总请求数')
                ->formatStateUsing(fn ($state) => number_format($state)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->paginated(false);
    }
}
