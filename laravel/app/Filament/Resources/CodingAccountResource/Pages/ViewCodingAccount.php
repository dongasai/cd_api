<?php

namespace App\Filament\Resources\CodingAccountResource\Pages;

use App\Filament\Resources\CodingAccountResource;
use App\Services\CodingStatus\CodingStatusDriverManager;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCodingAccount extends ViewRecord
{
    protected static string $resource = CodingAccountResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基本信息')
                    ->schema([
                        TextEntry::make('name')
                            ->label('账户名称'),

                        TextEntry::make('platform')
                            ->label('平台类型')
                            ->formatStateUsing(fn (string $state): string => \App\Models\CodingAccount::getPlatforms()[$state] ?? $state)
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'aliyun' => 'info',
                                'volcano' => 'danger',
                                'zhipu' => 'success',
                                'github' => 'gray',
                                'cursor' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('driver_class')
                            ->label('驱动类型')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('status')
                            ->label('账户状态')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'warning' => 'warning',
                                'critical' => 'danger',
                                'exhausted' => 'gray',
                                'expired' => 'gray',
                                'suspended' => 'gray',
                                'error' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => \App\Models\CodingAccount::getStatuses()[$state] ?? $state),
                    ])
                    ->columns(2),

                Section::make('配额信息')
                    ->schema([
                        TextEntry::make('quota_config')
                            ->label('配额配置')
                            ->formatStateUsing(function ($state) {
                                if (is_array($state)) {
                                    return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                }

                                return $state;
                            })
                            ->prose()
                            ->markdown()
                            ->extraAttributes(['class' => 'font-mono text-sm']),

                        TextEntry::make('quota_cached')
                            ->label('缓存的配额信息')
                            ->formatStateUsing(function ($state) {
                                if (is_array($state)) {
                                    return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                }

                                return $state ?? '无缓存数据';
                            })
                            ->prose()
                            ->markdown()
                            ->extraAttributes(['class' => 'font-mono text-sm']),
                    ]),

                Section::make('同步信息')
                    ->schema([
                        TextEntry::make('last_sync_at')
                            ->label('最后同步时间')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('未同步'),

                        TextEntry::make('sync_error')
                            ->label('同步错误')
                            ->placeholder('无错误')
                            ->color('danger'),

                        TextEntry::make('sync_error_count')
                            ->label('连续错误次数')
                            ->placeholder('0'),
                    ])
                    ->columns(3),

                Section::make('时间信息')
                    ->schema([
                        TextEntry::make('expires_at')
                            ->label('过期时间')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('永不过期'),

                        TextEntry::make('created_at')
                            ->label('创建时间')
                            ->dateTime('Y-m-d H:i:s'),

                        TextEntry::make('updated_at')
                            ->label('更新时间')
                            ->dateTime('Y-m-d H:i:s'),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync')
                ->label('同步配额')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $manager = app(CodingStatusDriverManager::class);
                        $driver = $manager->driverForAccount($this->record);
                        $driver->sync();

                        $this->notify('success', '配额同步成功');
                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (\Exception $e) {
                        $this->notify('danger', '同步失败: '.$e->getMessage());
                    }
                }),

            Actions\EditAction::make(),
        ];
    }
}
