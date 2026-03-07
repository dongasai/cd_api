<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('request_id')
                    ->label('请求ID')
                    ->searchable()
                    ->copyable()
                    ->limit(20),

                TextColumn::make('username')
                    ->label('用户')
                    ->searchable()
                    ->placeholder('匿名'),

                TextColumn::make('api_key_name')
                    ->label('API密钥')
                    ->searchable()
                    ->placeholder('无'),

                TextColumn::make('channel_name')
                    ->label('渠道')
                    ->searchable()
                    ->placeholder('无'),

                TextColumn::make('request_type')
                    ->label('请求类型')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => AuditLog::getRequestTypes()[$state] ?? '未知')
                    ->colors([
                        'success' => AuditLog::REQUEST_TYPE_CHAT,
                        'info' => AuditLog::REQUEST_TYPE_COMPLETION,
                        'warning' => AuditLog::REQUEST_TYPE_EMBEDDING,
                        'gray' => AuditLog::REQUEST_TYPE_OTHER,
                    ]),

                TextColumn::make('model')
                    ->label('模型')
                    ->searchable()
                    ->limit(20),

                TextColumn::make('status_code')
                    ->label('状态码')
                    ->badge()
                    ->colors([
                        'success' => fn ($state) => $state >= 200 && $state < 300,
                        'warning' => fn ($state) => $state >= 300 && $state < 400,
                        'danger' => fn ($state) => $state >= 400 || $state === null,
                    ]),

                TextColumn::make('total_tokens')
                    ->label('Token数')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state)),

                TextColumn::make('cost')
                    ->label('成本($)')
                    ->numeric(decimalPlaces: 6)
                    ->sortable(),

                TextColumn::make('latency_ms')
                    ->label('延迟(ms)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state)),

                IconColumn::make('is_stream')
                    ->label('流式')
                    ->boolean(),

                TextColumn::make('client_ip')
                    ->label('客户端IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('request_type')
                    ->label('请求类型')
                    ->options(AuditLog::getRequestTypes()),

                SelectFilter::make('billing_source')
                    ->label('计费来源')
                    ->options(AuditLog::getBillingSources()),

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
