<?php

namespace App\Filament\Resources\Channels\Pages;

use App\Filament\Resources\Channels\ChannelResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewChannel extends ViewRecord
{
    protected static string $resource = ChannelResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Section::make('基本信息')
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID'),
                                TextEntry::make('name')
                                    ->label('渠道名称'),
                                TextEntry::make('slug')
                                    ->label('渠道标识')
                                    ->placeholder('无'),
                                TextEntry::make('provider')
                                    ->label('提供商'),
                                TextEntry::make('base_url')
                                    ->label('API基础URL')
                                    ->placeholder('默认'),
                                TextEntry::make('description')
                                    ->label('描述')
                                    ->placeholder('无')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(1),

                        Section::make('状态信息')
                            ->schema([
                                TextEntry::make('status')
                                    ->label('运营状态')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'active' => '启用',
                                        'disabled' => '禁用',
                                        'maintenance' => '维护中',
                                        default => $state,
                                    })
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'disabled' => 'danger',
                                        'maintenance' => 'warning',
                                        default => 'gray',
                                    }),
                                IconEntry::make('health_status')
                                    ->label('健康状态')
                                    ->boolean()
                                    ->formatStateUsing(fn (string $state): bool => $state === 'healthy'),
                                TextEntry::make('weight')
                                    ->label('权重'),
                                TextEntry::make('priority')
                                    ->label('优先级'),
                            ])
                            ->columnSpan(1),

                        Section::make('继承信息')
                            ->schema([
                                TextEntry::make('inherit_mode')
                                    ->label('继承模式')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'merge' => '合并',
                                        'override' => '覆盖',
                                        'extend' => '扩展',
                                        default => $state,
                                    }),
                                TextEntry::make('parent.name')
                                    ->label('父渠道')
                                    ->placeholder('无'),
                            ])
                            ->columnSpan(1),

                        Section::make('统计数据')
                            ->schema([
                                TextEntry::make('total_requests')
                                    ->label('总请求数')
                                    ->formatStateUsing(fn ($state) => number_format($state)),
                                TextEntry::make('total_tokens')
                                    ->label('总Token数')
                                    ->formatStateUsing(fn ($state) => number_format($state)),
                                TextEntry::make('total_cost')
                                    ->label('总成本')
                                    ->prefix('$')
                                    ->formatStateUsing(fn ($state) => number_format($state, 4)),
                                TextEntry::make('avg_latency_ms')
                                    ->label('平均延迟(ms)'),
                                TextEntry::make('success_rate')
                                    ->label('成功率')
                                    ->formatStateUsing(fn ($state) => number_format($state * 100, 2).'%'),
                                TextEntry::make('failure_count')
                                    ->label('连续失败次数'),
                                TextEntry::make('success_count')
                                    ->label('连续成功次数'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),

                        Section::make('时间信息')
                            ->schema([
                                TextEntry::make('last_check_at')
                                    ->label('最后检查时间')
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('无'),
                                TextEntry::make('last_success_at')
                                    ->label('最后成功时间')
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('无'),
                                TextEntry::make('last_failure_at')
                                    ->label('最后失败时间')
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('无'),
                                TextEntry::make('created_at')
                                    ->label('创建时间')
                                    ->dateTime('Y-m-d H:i:s'),
                                TextEntry::make('updated_at')
                                    ->label('更新时间')
                                    ->dateTime('Y-m-d H:i:s'),
                            ])
                            ->columns(3)
                            ->columnSpan(2),

                        Section::make('Coding账户')
                            ->schema([
                                TextEntry::make('codingAccount.name')
                                    ->label('关联账户')
                                    ->placeholder('无')
                                    ->url(fn ($record) => $record->codingAccount
                                        ? \App\Filament\Resources\CodingAccounts\CodingAccountResource::getUrl('view', ['record' => $record->codingAccount])
                                        : null),
                                TextEntry::make('coding_status_override')
                                    ->label('状态覆盖配置')
                                    ->placeholder('无')
                                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(1),

                        Section::make('配置')
                            ->schema([
                                TextEntry::make('config')
                                    ->label('额外配置')
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
            EditAction::make(),
        ];
    }
}
