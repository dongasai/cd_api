<?php

namespace App\Filament\Resources\RequestLogs\Pages;

use App\Filament\Resources\RequestLogs\RequestLogResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewRequestLog extends ViewRecord
{
    protected static string $resource = RequestLogResource::class;

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
                                TextEntry::make('auditLog.request_id')
                                    ->label('请求ID')
                                    ->copyable(),
                                TextEntry::make('method')
                                    ->label('HTTP方法')
                                    ->badge(),
                                TextEntry::make('path')
                                    ->label('请求路径'),
                                TextEntry::make('query_string')
                                    ->label('查询参数')
                                    ->placeholder('无'),
                                TextEntry::make('created_at')
                                    ->label('创建时间')
                                    ->dateTime('Y-m-d H:i:s'),
                            ])
                            ->columnSpan(1),

                        Section::make('内容信息')
                            ->schema([
                                TextEntry::make('content_type')
                                    ->label('Content-Type')
                                    ->placeholder('无'),
                                TextEntry::make('content_length')
                                    ->label('内容长度')
                                    ->formatStateUsing(fn ($state) => number_format($state).' B'),
                                TextEntry::make('model')
                                    ->label('请求模型')
                                    ->placeholder('无'),
                                IconEntry::make('has_sensitive')
                                    ->label('包含敏感信息')
                                    ->boolean(),
                            ])
                            ->columnSpan(1),

                        Section::make('请求头')
                            ->schema([
                                TextEntry::make('headers')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Section::make('模型参数')
                            ->schema([
                                TextEntry::make('model_params')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->model_params)),

                        Section::make('消息内容')
                            ->schema([
                                TextEntry::make('messages')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->messages)),

                        Section::make('提示词')
                            ->schema([
                                TextEntry::make('prompt')
                                    ->label('')
                                    ->placeholder('无')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->prompt)),

                        Section::make('请求体')
                            ->schema([
                                TextEntry::make('body_text')
                                    ->label('')
                                    ->placeholder('无')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->body_text)),

                        Section::make('敏感字段')
                            ->schema([
                                TextEntry::make('sensitive_fields')
                                    ->label('')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->sensitive_fields)),

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
                    ? \App\Filament\Resources\AuditLogs\AuditLogResource::getUrl('index')
                    : null)
                ->visible(fn ($record) => $record->auditLog !== null)
                ->openUrlInNewTab(),
        ];
    }
}
