<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Grid::make(3)
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
                            ->columnSpan(3),

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
                                TextEntry::make('is_stream')
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
                            ->columnSpan(3)
                            ->visible(fn ($record) => filled($record->error_type)),

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
                            ->columnSpan(3),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_request')
                ->label('查看请求日志')
                ->icon('heroicon-o-arrow-right')
                ->url(fn ($record) => $record->requestLog
                    ? \App\Filament\Resources\RequestLogs\RequestLogResource::getUrl('view', ['record' => $record->requestLog])
                    : null)
                ->visible(fn ($record) => $record->requestLog !== null)
                ->openUrlInNewTab(),

            Action::make('view_response')
                ->label('查看响应日志')
                ->icon('heroicon-o-arrow-right')
                ->url(fn ($record) => $record->responseLog
                    ? \App\Filament\Resources\ResponseLogs\ResponseLogResource::getUrl('view', ['record' => $record->responseLog])
                    : null)
                ->visible(fn ($record) => $record->responseLog !== null)
                ->openUrlInNewTab(),
        ];
    }
}
