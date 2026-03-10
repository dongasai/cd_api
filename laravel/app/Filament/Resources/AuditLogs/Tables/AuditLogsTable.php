<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
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

                TextColumn::make('user_info')
                    ->label('用户 / API密钥 / 渠道')
                    ->html()
                    ->searchable(['username', 'api_key_name', 'channel_name'])
                    ->state(function (AuditLog $record): string {
                        $lines = [];

                        $lines[] = '<div class="space-y-1">';

                        if ($record->username) {
                            $lines[] = '<div><span class="text-gray-500">用户:</span> '.e($record->username).'</div>';
                        }

                        if ($record->api_key_name) {
                            $lines[] = '<div><span class="text-gray-500">密钥:</span> '.e($record->api_key_name).'</div>';
                        }

                        if ($record->channel_name) {
                            $lines[] = '<div><span class="text-gray-500">渠道:</span> '.e($record->channel_name).'</div>';
                        }

                        if (empty($record->username) && empty($record->api_key_name) && empty($record->channel_name)) {
                            $lines[] = '<div class="text-gray-400">-</div>';
                        }

                        $lines[] = '</div>';

                        return implode('', $lines);
                    }),

                TextColumn::make('request_type_stream')
                    ->label('请求类型 / 流式')
                    ->html()
                    ->state(function (AuditLog $record): string {
                        $typeLabel = AuditLog::getRequestTypes()[$record->request_type] ?? '未知';
                        $typeColors = [
                            AuditLog::REQUEST_TYPE_CHAT => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                            AuditLog::REQUEST_TYPE_COMPLETION => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                            AuditLog::REQUEST_TYPE_EMBEDDING => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                            AuditLog::REQUEST_TYPE_OTHER => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
                        ];
                        $typeColor = $typeColors[$record->request_type] ?? $typeColors[AuditLog::REQUEST_TYPE_OTHER];

                        $streamBadge = $record->is_stream
                            ? '<span class="inline-flex items-center rounded-md bg-primary-100 px-2 py-1 text-xs font-medium text-primary-700 dark:bg-primary-900 dark:text-primary-300 ml-1">流式</span>'
                            : '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400 ml-1">非流式</span>';

                        return '<div class="flex items-center gap-1">'.
                            '<span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium '.$typeColor.'">'.$typeLabel.'</span>'.
                            $streamBadge.
                            '</div>';
                    }),

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

                TextColumn::make('affinity')
                    ->label('亲和性')
                    ->html()
                    ->state(function (AuditLog $record): string {
                        $affinity = $record->channel_affinity;
                        if (empty($affinity)) {
                            return '<span class="text-gray-400">-</span>';
                        }

                        $isHit = $affinity['is_affinity_hit'] ?? false;
                        if ($isHit) {
                            return '<span class="inline-flex items-center rounded-md bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">命中</span>';
                        }

                        return '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">未命中</span>';
                    }),

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
            ], layout: FiltersLayout::AboveContent)
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
