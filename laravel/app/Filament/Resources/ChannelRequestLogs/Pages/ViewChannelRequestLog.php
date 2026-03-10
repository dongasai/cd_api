<?php

namespace App\Filament\Resources\ChannelRequestLogs\Pages;

use App\Filament\Resources\ChannelRequestLogs\ChannelRequestLogResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Phiki\Grammar\Grammar;

class ViewChannelRequestLog extends ViewRecord
{
    protected static string $resource = ChannelRequestLogResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make('基本信息')
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID'),
                                TextEntry::make('request_id')
                                    ->label('请求 ID')
                                    ->copyable(),
                                TextEntry::make('channel.name')
                                    ->label('渠道')
                                    ->placeholder('未知'),
                                TextEntry::make('provider')
                                    ->label('提供商')
                                    ->badge(),
                                TextEntry::make('method')
                                    ->label('HTTP 方法')
                                    ->badge(),
                                TextEntry::make('path')
                                    ->label('请求路径')
                                    ->columnSpanFull(),
                                TextEntry::make('base_url')
                                    ->label('Base URL')
                                    ->columnSpanFull()
                                    ->placeholder('无'),
                                TextEntry::make('full_url')
                                    ->label('完整 URL')
                                    ->columnSpanFull()
                                    ->placeholder('无'),
                            ])
                            ->columnSpan(1),

                        Section::make('请求结果')
                            ->schema([
                                TextEntry::make('response_status')
                                    ->label('状态码')
                                    ->badge()
                                    ->colors([
                                        'success' => fn ($state) => $state >= 200 && $state < 300,
                                        'warning' => fn ($state) => $state >= 300 && $state < 400,
                                        'danger' => fn ($state) => $state >= 400,
                                    ])
                                    ->placeholder('无'),
                                IconEntry::make('is_success')
                                    ->label('请求成功')
                                    ->boolean(),
                                TextEntry::make('latency_ms')
                                    ->label('请求延迟')
                                    ->formatStateUsing(fn ($state) => number_format($state).' ms'),
                                TextEntry::make('ttfb_ms')
                                    ->label('首字节时间')
                                    ->formatStateUsing(fn ($state) => number_format($state).' ms')
                                    ->placeholder('无'),
                                TextEntry::make('request_size')
                                    ->label('请求大小')
                                    ->formatStateUsing(fn ($state) => number_format($state).' B'),
                                TextEntry::make('response_size')
                                    ->label('响应大小')
                                    ->formatStateUsing(fn ($state) => number_format($state).' B'),
                                TextEntry::make('error_type')
                                    ->label('错误类型')
                                    ->placeholder('无'),
                                TextEntry::make('error_message')
                                    ->label('错误消息')
                                    ->placeholder('无')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(1),

                        Section::make('时间信息')
                            ->schema([
                                TextEntry::make('sent_at')
                                    ->label('发送时间')
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('无'),
                                TextEntry::make('created_at')
                                    ->label('创建时间')
                                    ->dateTime('Y-m-d H:i:s'),
                                TextEntry::make('updated_at')
                                    ->label('更新时间')
                                    ->dateTime('Y-m-d H:i:s'),
                            ])
                            ->columnSpanFull(),

                        Section::make('请求头')
                            ->schema([
                                TextEntry::make('request_headers')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Section::make('请求体')
                            ->schema([
                                CodeEntry::make('request_body')
                                    ->label('')
                                    ->placeholder('无')
                                    ->grammar(Grammar::Json)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->request_body)),

                        Section::make('响应头')
                            ->schema([
                                TextEntry::make('response_headers')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Section::make('响应体')
                            ->schema([
                                CodeEntry::make('response_body')
                                    ->label('')
                                    ->placeholder('无')
                                    ->grammar(Grammar::Json)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->response_body)),

                        Section::make('Token 使用')
                            ->schema([
                                TextEntry::make('usage')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->usage)),

                        Section::make('元数据')
                            ->schema([
                                TextEntry::make('metadata')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
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

            Action::make('view_channel')
                ->label('查看渠道')
                ->icon('heroicon-o-server')
                ->url(fn ($record) => $record->channel
                    ? \App\Filament\Resources\Channels\ChannelResource::getUrl('edit', ['record' => $record->channel])
                    : null)
                ->visible(fn ($record) => $record->channel !== null)
                ->openUrlInNewTab(),
        ];
    }
}
