<?php

namespace App\Filament\Resources\ChannelRequestLogs\Tables;

use App\Models\ChannelRequestLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChannelRequestLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('request_id')
                    ->label('请求 ID')
                    ->searchable()
                    ->copyable()
                    ->limit(20),

                TextColumn::make('channel.name')
                    ->label('渠道')
                    ->searchable()
                    ->placeholder('未知'),

                TextColumn::make('provider')
                    ->label('提供商')
                    ->searchable()
                    ->badge(),

                TextColumn::make('method')
                    ->label('方法')
                    ->badge()
                    ->colors([
                        'success' => 'GET',
                        'info' => 'POST',
                        'warning' => 'PUT',
                        'danger' => 'DELETE',
                        'gray' => 'PATCH',
                    ]),

                TextColumn::make('path')
                    ->label('路径')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('response_status')
                    ->label('状态码')
                    ->badge()
                    ->colors([
                        'success' => fn ($state) => $state >= 200 && $state < 300,
                        'warning' => fn ($state) => $state >= 300 && $state < 400,
                        'danger' => fn ($state) => $state >= 400,
                    ])
                    ->placeholder('无'),

                TextColumn::make('latency_ms')
                    ->label('延迟 (ms)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state).' ms'),

                TextColumn::make('request_size')
                    ->label('请求大小')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state).' B')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('response_size')
                    ->label('响应大小')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state).' B')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_success')
                    ->label('成功')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label('发送时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label('提供商')
                    ->options(function (): array {
                        $providers = ChannelRequestLog::query()
                            ->distinct()
                            ->pluck('provider')
                            ->filter()
                            ->toArray();

                        return array_combine($providers, $providers);
                    }),

                SelectFilter::make('is_success')
                    ->label('请求结果')
                    ->options([
                        '1' => '成功',
                        '0' => '失败',
                    ]),

                Filter::make('sent_at')
                    ->label('发送时间')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('从'),
                        \Filament\Forms\Components\DatePicker::make('to')
                            ->label('到'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('sent_at', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('sent_at', '<=', $date),
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sent_at', 'desc');
    }
}
