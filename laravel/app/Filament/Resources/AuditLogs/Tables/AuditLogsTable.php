<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
                Split::make([
                    TextColumn::make('id')
                        ->label('ID')
                        ->weight(FontWeight::Bold)
                        ->sortable()
                        ->width('70px'),

                    TextColumn::make('created_at')
                        ->label('时间')
                        ->weight(FontWeight::Bold)
                        ->dateTime('m-d H:i:s')
                        ->sortable()
                        ->width('120px'),

                    TextColumn::make('channel_name')
                        ->label('渠道')
                        ->searchable()
                        ->limit(15)
                        ->placeholder('-')
                        ->width('120px'),

                    TextColumn::make('total_tokens')
                        ->label('令牌')
                        ->numeric()
                        ->sortable()
                        ->formatStateUsing(fn ($state) => number_format($state))
                        ->width('80px'),

                    TextColumn::make('model')
                        ->label('模型')
                        ->searchable()
                        ->limit(20)
                        ->placeholder('-'),

                    TextColumn::make('latency_info')
                        ->label('用时/首字')
                        ->html()
                        ->width('100px')
                        ->state(function (AuditLog $record): string {
                            $latency = number_format($record->latency_ms ?? 0);
                            $firstToken = number_format($record->first_token_ms ?? 0);

                            return '<div class="text-sm">'.
                                '<div><span class="text-gray-500">总:</span> '.$latency.'ms</div>'.
                                '<div><span class="text-gray-500">首:</span> '.$firstToken.'ms</div>'.
                                '</div>';
                        }),

                    TextColumn::make('prompt_tokens')
                        ->label('输入')
                        ->numeric()
                        ->sortable()
                        ->formatStateUsing(fn ($state) => number_format($state))
                        ->width('70px'),

                    TextColumn::make('completion_tokens')
                        ->label('输出')
                        ->numeric()
                        ->sortable()
                        ->formatStateUsing(fn ($state) => number_format($state))
                        ->width('70px'),
                ]),

                Panel::make([
                    Stack::make([
                        TextColumn::make('channel_detail')
                            ->label('渠道信息')
                            ->html()
                            ->state(function (AuditLog $record): string {
                                $html = '<div class="grid grid-cols-3 gap-4">';
                                $html .= '<div><span class="text-gray-500">渠道:</span> '.e($record->channel_name ?? '-').'</div>';
                                $html .= '<div><span class="text-gray-500">用户:</span> '.e($record->username ?? '-').'</div>';
                                $html .= '<div><span class="text-gray-500">密钥:</span> '.e($record->api_key_name ?? '-').'</div>';
                                $html .= '</div>';

                                return $html;
                            }),

                        TextColumn::make('request_id')
                            ->label('Request ID')
                            ->copyable()
                            ->state(fn (AuditLog $record): string => $record->request_id ?? '-'),

                        TextColumn::make('tokens_detail')
                            ->label('Tokens详细')
                            ->html()
                            ->state(function (AuditLog $record): string {
                                $html = '<div class="flex items-center gap-6">';
                                $html .= '<div><span class="text-gray-500">输入:</span> '.number_format($record->prompt_tokens ?? 0).'</div>';
                                $html .= '<div><span class="text-gray-500">缓存读:</span> '.number_format($record->cache_read_tokens ?? 0).'</div>';
                                $html .= '<div><span class="text-gray-500">缓存写:</span> '.number_format($record->cache_write_tokens ?? 0).'</div>';
                                $html .= '<div><span class="text-gray-500">输出:</span> '.number_format($record->completion_tokens ?? 0).'</div>';
                                $html .= '</div>';

                                return $html;
                            }),

                        TextColumn::make('request_path')
                            ->label('请求路径')
                            ->html()
                            ->state(function (AuditLog $record): string {
                                $path = $record->requestLog?->path ?? '-';
                                $method = $record->requestLog?->method ?? 'POST';

                                return '<code class="text-sm bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">'.$method.' '.$path.'</code>';
                            }),

                        TextColumn::make('model_transform')
                            ->label('请求转换')
                            ->html()
                            ->state(function (AuditLog $record): string {
                                $requestModel = $record->model ?? '-';
                                $actualModel = $record->actual_model ?? '-';

                                if ($requestModel === $actualModel) {
                                    return '<span class="text-gray-500">无转换</span>';
                                }

                                return '<span class="text-primary-600">'.e($requestModel).'</span>'.
                                    ' <span class="text-gray-400">→</span> '.
                                    '<span class="text-success-600">'.e($actualModel).'</span>';
                            }),

                        TextColumn::make('extra_info')
                            ->label('其他信息')
                            ->html()
                            ->state(function (AuditLog $record): string {
                                $affinity = $record->channel_affinity;
                                $affinityHtml = '<span class="text-gray-400">-</span>';
                                if (! empty($affinity)) {
                                    $isHit = $affinity['is_affinity_hit'] ?? false;
                                    $affinityHtml = $isHit
                                        ? '<span class="inline-flex items-center rounded-md bg-green-100 px-2 py-1 text-xs font-medium text-green-800">命中</span>'
                                        : '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">未命中</span>';
                                }

                                $statusBadge = match (true) {
                                    ($record->status_code >= 200 && $record->status_code < 300) => '<span class="inline-flex items-center rounded-md bg-green-100 px-2 py-1 text-xs font-medium text-green-800">'.$record->status_code.'</span>',
                                    ($record->status_code >= 400) => '<span class="inline-flex items-center rounded-md bg-red-100 px-2 py-1 text-xs font-medium text-red-800">'.$record->status_code.'</span>',
                                    default => '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">'.$record->status_code.'</span>',
                                };

                                $streamBadge = $record->is_stream
                                    ? '<span class="inline-flex items-center rounded-md bg-primary-100 px-2 py-1 text-xs font-medium text-primary-700">流式</span>'
                                    : '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">非流式</span>';

                                $html = '<div class="flex items-center gap-4">';
                                $html .= '<div><span class="text-gray-500">状态:</span> '.$statusBadge.'</div>';
                                $html .= '<div><span class="text-gray-500">流式:</span> '.$streamBadge.'</div>';
                                $html .= '<div><span class="text-gray-500">亲和性:</span> '.$affinityHtml.'</div>';
                                $html .= '<div><span class="text-gray-500">成本:</span> $'.number_format($record->cost ?? 0, 6).'</div>';
                                $html .= '</div>';

                                return $html;
                            }),

                        TextColumn::make('error_detail')
                            ->label('错误信息')
                            ->html()
                            ->visible(fn (?AuditLog $record): bool => $record && ! empty($record->error_message))
                            ->state(function (AuditLog $record): string {
                                $html = '<div class="text-danger-600 dark:text-danger-400">';
                                $html .= '<span class="font-medium">错误:</span> '.e($record->error_type ?? '未知类型').' - '.e($record->error_message ?? '-');
                                $html .= '</div>';

                                return $html;
                            }),
                    ]),
                ])->collapsible(),
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
