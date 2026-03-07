<?php

namespace App\Filament\Resources\ResponseLogs\Tables;

use App\Models\ResponseLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResponseLogsTable
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

                TextColumn::make('status_code')
                    ->label('状态码')
                    ->badge()
                    ->colors([
                        'success' => fn ($state) => $state >= 200 && $state < 300,
                        'warning' => fn ($state) => $state >= 300 && $state < 400,
                        'danger' => fn ($state) => $state >= 400 || $state === null,
                    ]),

                TextColumn::make('response_type')
                    ->label('响应类型')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ResponseLog::getResponseTypes()[$state] ?? $state : '无')
                    ->colors([
                        'success' => ResponseLog::RESPONSE_TYPE_CHAT,
                        'info' => ResponseLog::RESPONSE_TYPE_COMPLETION,
                        'warning' => ResponseLog::RESPONSE_TYPE_EMBEDDING,
                        'danger' => ResponseLog::RESPONSE_TYPE_ERROR,
                    ]),

                TextColumn::make('upstream_provider')
                    ->label('上游提供商')
                    ->placeholder('无')
                    ->searchable(),

                TextColumn::make('upstream_model')
                    ->label('上游模型')
                    ->placeholder('无')
                    ->searchable()
                    ->limit(20),

                TextColumn::make('content_length')
                    ->label('内容长度')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state) . ' B'),

                TextColumn::make('upstream_latency_ms')
                    ->label('上游延迟(ms)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state)),

                TextColumn::make('finish_reason')
                    ->label('结束原因')
                    ->placeholder('无'),

                TextColumn::make('error_type')
                    ->label('错误类型')
                    ->placeholder('无')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('response_type')
                    ->label('响应类型')
                    ->options(ResponseLog::getResponseTypes()),

                SelectFilter::make('status_code')
                    ->label('状态码')
                    ->options([
                        '200' => '200 OK',
                        '201' => '201 Created',
                        '400' => '400 Bad Request',
                        '401' => '401 Unauthorized',
                        '403' => '403 Forbidden',
                        '404' => '404 Not Found',
                        '429' => '429 Too Many Requests',
                        '500' => '500 Internal Server Error',
                        '502' => '502 Bad Gateway',
                        '503' => '503 Service Unavailable',
                    ]),

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
