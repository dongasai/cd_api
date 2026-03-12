<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
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
                    ->weight(FontWeight::Bold)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime('m-d H:i:s')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('channel_name')
                    ->label('渠道')
                    ->searchable()
                    ->limit(20)
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('model_info')
                    ->label('模型')
                    ->html()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->where('model', 'like', "%{$search}%")
                                ->orWhere('actual_model', 'like', "%{$search}%");
                        });
                    })
                    ->state(function (AuditLog $record): string {
                        $requestModel = $record->model ?? '-';
                        $actualModel = $record->actual_model ?? '-';

                        if ($requestModel === $actualModel) {
                            return '<div class="text-sm">'.e($requestModel).'</div>';
                        }

                        return '<div class="text-sm leading-tight">'.
                            '<div><span class="text-gray-400 text-xs">请求:</span> <span class="text-primary-600">'.e($requestModel).'</span></div>'.
                            '<div><span class="text-gray-400 text-xs">实际:</span> <span class="text-success-600">'.e($actualModel).'</span></div>'.
                            '</div>';
                    }),

                TextColumn::make('tokens_info')
                    ->label('令牌')
                    ->html()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('total_tokens', $direction);
                    })
                    ->state(function (AuditLog $record): string {
                        $total = number_format($record->total_tokens ?? 0);
                        $prompt = number_format($record->prompt_tokens ?? 0);
                        $completion = number_format($record->completion_tokens ?? 0);

                        return '<div class="text-sm leading-tight">'.
                            '<div class="flex gap-3">'.
                            '<span><span class="text-primary-600">'.$prompt.'</span> <span class="text-gray-400 text-xs">入</span></span>'.
                            '<span><span class="text-success-600">'.$completion.'</span> <span class="text-gray-400 text-xs">出</span></span>'.
                            '</div>'.
                            '<div><span class="font-medium">'.$total.'</span> <span class="text-gray-400 text-xs">总</span></div>'.
                            '</div>';
                    }),

                TextColumn::make('latency_info')
                    ->label('耗时')
                    ->html()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('latency_ms', $direction);
                    })
                    ->state(function (AuditLog $record): string {
                        $latency = number_format(($record->latency_ms ?? 0) / 1000, 2);
                        $firstToken = number_format(($record->first_token_ms ?? 0) / 1000, 2);

                        return '<div class="text-sm leading-tight">'.
                            '<div><span class="font-medium">'.$latency.'</span><span class="text-gray-400 text-xs">s</span> <span class="text-gray-400 text-xs">总</span></div>'.
                            '<div><span class="text-primary-600">'.$firstToken.'</span><span class="text-gray-400 text-xs">s</span> <span class="text-gray-400 text-xs">首字</span></div>'.
                            '</div>';
                    }),

                TextColumn::make('status_code')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => (string) $state)
                    ->color(fn (int $state): string => match (true) {
                        ($state >= 200 && $state < 300) => 'success',
                        ($state >= 400) => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('cost')
                    ->label('成本')
                    ->money('USD', divideBy: 1)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('is_stream')
                    ->label('流式')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '是' : '否')
                    ->color(fn (bool $state): string => $state ? 'primary' : 'gray'),

                TextColumn::make('affinity_hit')
                    ->label('亲和命中')
                    ->badge()
                    ->state(function (AuditLog $record): string {
                        $affinity = $record->channel_affinity;
                        if (empty($affinity)) {
                            return '-';
                        }

                        return ($affinity['is_affinity_hit'] ?? false) ? '命中' : '未命中';
                    })
                    ->color(function (AuditLog $record): string {
                        $affinity = $record->channel_affinity;
                        if (empty($affinity)) {
                            return 'gray';
                        }

                        return ($affinity['is_affinity_hit'] ?? false) ? 'success' : 'warning';
                    }),

                TextColumn::make('username')
                    ->label('用户')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('api_key_name')
                    ->label('密钥')
                    ->searchable()
                    ->limit(15)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('request_id')
                    ->label('Request ID')
                    ->copyable()
                    ->limit(20)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Action::make('view')
                    ->label('查看')
                    ->icon(Heroicon::Eye)
                    ->color('gray')
                    ->modalHeading('审计日志详情')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭')
                    ->schema(fn (AuditLog $record): array => self::getInfolistSchema($record))
                    ->extraModalFooterActions(fn (AuditLog $record): array => [
                        Action::make('view_api_key')
                            ->label('查看API密钥')
                            ->icon(Heroicon::Key)
                            ->color('primary')
                            ->url(fn () => $record->apiKey
                                ? \App\Filament\Resources\ApiKeys\ApiKeyResource::getUrl('view', ['record' => $record->apiKey])
                                : null)
                            ->visible(filled($record->apiKey))
                            ->openUrlInNewTab(),
                        Action::make('view_channel')
                            ->label('查看渠道')
                            ->icon(Heroicon::ServerStack)
                            ->color('primary')
                            ->url(fn () => $record->channel
                                ? \App\Filament\Resources\Channels\ChannelResource::getUrl('view', ['record' => $record->channel])
                                : null)
                            ->visible(filled($record->channel))
                            ->openUrlInNewTab(),
                        Action::make('view_request')
                            ->label('查看请求日志')
                            ->icon(Heroicon::ArrowRight)
                            ->color('primary')
                            ->url(fn () => $record->requestLog
                                ? \App\Filament\Resources\RequestLogs\RequestLogResource::getUrl('view', ['record' => $record->requestLog])
                                : null)
                            ->visible(filled($record->requestLog))
                            ->openUrlInNewTab(),
                        Action::make('view_response')
                            ->label('查看响应日志')
                            ->icon(Heroicon::ArrowRight)
                            ->color('primary')
                            ->url(fn () => $record->responseLog
                                ? \App\Filament\Resources\ResponseLogs\ResponseLogResource::getUrl('view', ['record' => $record->responseLog])
                                : null)
                            ->visible(filled($record->responseLog))
                            ->openUrlInNewTab(),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getInfolistSchema(AuditLog $record): array
    {
        return [
            Grid::make(3)
                ->schema([
                    Section::make('基本信息')
                        ->schema([
                            TextEntry::make('id')
                                ->label('ID'),
                            TextEntry::make('request_id')
                                ->label('请求ID')
                                ->copyable(),
                            TextEntry::make('request_type')
                                ->label('请求类型')
                                ->badge()
                                ->formatStateUsing(fn (int $state): string => AuditLog::getRequestTypes()[$state] ?? '未知'),
                            TextEntry::make('created_at')
                                ->label('创建时间')
                                ->dateTime('Y-m-d H:i:s'),
                        ])
                        ->columnSpan(1),

                    Section::make('用户信息')
                        ->schema([
                            TextEntry::make('username')
                                ->label('用户名')
                                ->placeholder('匿名'),
                            TextEntry::make('api_key_name')
                                ->label('API密钥')
                                ->placeholder('无'),
                            TextEntry::make('cached_key_prefix')
                                ->label('密钥前缀')
                                ->placeholder('无'),
                            TextEntry::make('client_ip')
                                ->label('客户端IP'),
                        ])
                        ->columnSpan(1),

                    Section::make('渠道信息')
                        ->schema([
                            TextEntry::make('channel_name')
                                ->label('渠道名称')
                                ->placeholder('无'),
                            TextEntry::make('model')
                                ->label('请求模型'),
                            TextEntry::make('actual_model')
                                ->label('实际模型')
                                ->placeholder('无'),
                            TextEntry::make('group_name')
                                ->label('分组')
                                ->placeholder('无'),
                        ])
                        ->columnSpan(1),

                    Section::make('Token 使用')
                        ->schema([
                            TextEntry::make('prompt_tokens')
                                ->label('输入Token'),
                            TextEntry::make('completion_tokens')
                                ->label('输出Token'),
                            TextEntry::make('total_tokens')
                                ->label('总Token'),
                            TextEntry::make('cache_read_tokens')
                                ->label('缓存读取')
                                ->placeholder('0'),
                            TextEntry::make('cache_write_tokens')
                                ->label('缓存写入')
                                ->placeholder('0'),
                        ])
                        ->columns(5)
                        ->columnSpanFull(),

                    Section::make('成本与配额')
                        ->schema([
                            TextEntry::make('cost')
                                ->label('成本')
                                ->prefix('$'),
                            TextEntry::make('quota')
                                ->label('配额消耗'),
                            TextEntry::make('billing_source')
                                ->label('计费来源')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => AuditLog::getBillingSources()[$state] ?? $state),
                        ])
                        ->columns(3)
                        ->columnSpan(1),

                    Section::make('响应信息')
                        ->schema([
                            TextEntry::make('status_code')
                                ->label('状态码')
                                ->badge(),
                            TextEntry::make('latency_ms')
                                ->label('总延迟(ms)'),
                            TextEntry::make('first_token_ms')
                                ->label('首Token延迟(ms)')
                                ->placeholder('无'),
                            IconEntry::make('is_stream')
                                ->label('流式请求')
                                ->boolean(),
                            TextEntry::make('finish_reason')
                                ->label('结束原因')
                                ->placeholder('无'),
                        ])
                        ->columns(3)
                        ->columnSpan(2),

                    Section::make('错误信息')
                        ->schema([
                            TextEntry::make('error_type')
                                ->label('错误类型')
                                ->placeholder('无'),
                            TextEntry::make('error_message')
                                ->label('错误消息')
                                ->placeholder('无')
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull()
                        ->visible(filled($record->error_type)),

                    Section::make('元数据')
                        ->schema([
                            TextEntry::make('channel_affinity')
                                ->label('渠道亲和性')
                                ->placeholder('无')
                                ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                            TextEntry::make('metadata')
                                ->label('元数据')
                                ->placeholder('无')
                                ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }
}
