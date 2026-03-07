<?php

namespace App\Filament\Resources\ResponseLogs\Pages;

use App\Filament\Resources\ResponseLogs\ResponseLogResource;
use App\Models\ResponseLog;
use Filament\Actions\Action;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewResponseLog extends ViewRecord
{
    protected static string $resource = ResponseLogResource::class;

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
                                TextEntry::make('auditLog.request_id')
                                    ->label('请求ID')
                                    ->copyable(),
                                TextEntry::make('status_code')
                                    ->label('HTTP状态码')
                                    ->badge(),
                                TextEntry::make('status_message')
                                    ->label('状态消息')
                                    ->placeholder('无'),
                                TextEntry::make('response_type')
                                    ->label('响应类型')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => $state ? ResponseLog::getResponseTypes()[$state] ?? $state : '无'),
                                TextEntry::make('created_at')
                                    ->label('创建时间')
                                    ->dateTime('Y-m-d H:i:s'),
                            ])
                            ->columnSpan(1),

                        Section::make('上游信息')
                            ->schema([
                                TextEntry::make('upstream_provider')
                                    ->label('上游提供商')
                                    ->placeholder('无'),
                                TextEntry::make('upstream_model')
                                    ->label('上游模型')
                                    ->placeholder('无'),
                                TextEntry::make('upstream_latency_ms')
                                    ->label('上游延迟(ms)'),
                            ])
                            ->columnSpan(1),

                        Section::make('内容信息')
                            ->schema([
                                TextEntry::make('content_type')
                                    ->label('Content-Type')
                                    ->placeholder('无'),
                                TextEntry::make('content_length')
                                    ->label('内容长度')
                                    ->formatStateUsing(fn ($state) => number_format($state) . ' B'),
                                TextEntry::make('finish_reason')
                                    ->label('结束原因')
                                    ->placeholder('无'),
                            ])
                            ->columnSpan(1),

                        Section::make('响应头')
                            ->schema([
                                TextEntry::make('headers')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(3),

                        Section::make('生成内容')
                            ->schema([
                                TextEntry::make('generated_text')
                                    ->label('生成文本')
                                    ->placeholder('无')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(3)
                            ->visible(fn ($record) => filled($record->generated_text)),

                        Section::make('流式分块')
                            ->schema([
                                TextEntry::make('generated_chunks')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(3)
                            ->visible(fn ($record) => filled($record->generated_chunks)),

                        Section::make('使用量')
                            ->schema([
                                TextEntry::make('usage')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(3)
                            ->visible(fn ($record) => filled($record->usage)),

                        Section::make('错误信息')
                            ->schema([
                                TextEntry::make('error_type')
                                    ->label('错误类型')
                                    ->placeholder('无'),
                                TextEntry::make('error_code')
                                    ->label('错误代码')
                                    ->placeholder('无'),
                                TextEntry::make('error_message')
                                    ->label('错误消息')
                                    ->placeholder('无')
                                    ->columnSpanFull(),
                                TextEntry::make('error_details')
                                    ->label('错误详情')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(3)
                            ->visible(fn ($record) => filled($record->error_type)),

                        Section::make('响应体')
                            ->schema([
                                TextEntry::make('body_text')
                                    ->label('')
                                    ->placeholder('无')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(3)
                            ->visible(fn ($record) => filled($record->body_text)),

                        Section::make('元数据')
                            ->schema([
                                TextEntry::make('metadata')
                                    ->label('')
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
            Action::make('view_audit')
                ->label('查看审计日志')
                ->icon('heroicon-o-arrow-right')
                ->url(fn ($record) => $record->auditLog
                    ? \App\Filament\Resources\AuditLogs\AuditLogResource::getUrl('view', ['record' => $record->auditLog])
                    : null)
                ->visible(fn ($record) => $record->auditLog !== null)
                ->openUrlInNewTab(),

            Action::make('view_request')
                ->label('查看请求日志')
                ->icon('heroicon-o-arrow-right')
                ->url(fn ($record) => $record->requestLog
                    ? \App\Filament\Resources\RequestLogs\RequestLogResource::getUrl('view', ['record' => $record->requestLog])
                    : null)
                ->visible(fn ($record) => $record->requestLog !== null)
                ->openUrlInNewTab(),
        ];
    }
}
