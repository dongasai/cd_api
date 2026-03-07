<?php

namespace App\Filament\Resources\RequestLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequestLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('auditLog.request_id')
                    ->label('请求ID')
                    ->searchable()
                    ->copyable()
                    ->limit(20),

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
                    ->limit(40),

                TextColumn::make('model')
                    ->label('模型')
                    ->searchable()
                    ->placeholder('无')
                    ->limit(20),

                TextColumn::make('content_type')
                    ->label('Content-Type')
                    ->placeholder('无')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('content_length')
                    ->label('内容长度')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state) . ' B'),

                IconColumn::make('has_sensitive')
                    ->label('含敏感信息')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('创建时间')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
