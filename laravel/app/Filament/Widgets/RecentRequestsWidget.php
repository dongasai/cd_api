<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentRequestsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 6;

    public function getHeading(): ?string
    {
        return '最近请求';
    }

    protected function getTableQuery(): Builder
    {
        return AuditLog::query()
            ->select(['id', 'request_id', 'model', 'status_code', 'total_tokens', 'cost', 'latency_ms', 'created_at', 'channel_name'])
            ->orderByDesc('created_at')
            ->limit(20);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('created_at')
                ->label('时间')
                ->dateTime('H:i:s')
                ->sortable(),

            TextColumn::make('request_id')
                ->label('请求ID')
                ->limit(12)
                ->copyable()
                ->tooltip(fn ($record) => $record->request_id),

            TextColumn::make('model')
                ->label('模型')
                ->limit(20)
                ->tooltip(fn ($record) => $record->model),

            TextColumn::make('channel_name')
                ->label('渠道')
                ->limit(15),

            TextColumn::make('status_code')
                ->label('状态')
                ->badge()
                ->color(fn (int $state): string => match (true) {
                    $state >= 200 && $state < 300 => 'success',
                    $state >= 400 && $state < 500 => 'warning',
                    $state >= 500 => 'danger',
                    default => 'gray',
                }),

            TextColumn::make('total_tokens')
                ->label('Token')
                ->formatStateUsing(fn ($state) => $state ? number_format($state) : '-'),

            TextColumn::make('cost')
                ->label('费用')
                ->formatStateUsing(fn ($state) => $state ? '$'.number_format($state, 6) : '-'),

            TextColumn::make('latency_ms')
                ->label('延迟')
                ->formatStateUsing(fn ($state) => $state ? $state.'ms' : '-'),
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
