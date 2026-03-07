<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Filament\Resources\ApiKeys\ApiKeyResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewApiKey extends ViewRecord
{
    protected static string $resource = ApiKeyResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 第一行：基本信息 + 密钥信息 + 时间信息
                Grid::make(3)
                    ->schema([
                        Section::make('基本信息')
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID'),
                                TextEntry::make('name')
                                    ->label('名称'),
                                TextEntry::make('status')
                                    ->label('状态')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'active' => '启用',
                                        'revoked' => '已撤销',
                                        'expired' => '已过期',
                                        default => $state,
                                    })
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'revoked' => 'danger',
                                        'expired' => 'warning',
                                        default => 'gray',
                                    }),
                            ])
                            ->columnSpan(1),

                        Section::make('密钥信息')
                            ->schema([
                                TextEntry::make('key')
                                    ->label('密钥')
                                    ->copyable()
                                    ->copyMessage('密钥已复制到剪贴板')
                                    ->formatStateUsing(fn ($state) => $state ?? '-'),
                                TextEntry::make('key_prefix')
                                    ->label('密钥前缀')
                                    ->placeholder('未设置'),
                            ])
                            ->columnSpan(1),

                        Section::make('时间信息')
                            ->schema([
                                TextEntry::make('expires_at')
                                    ->label('过期时间')
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('永不过期'),
                                TextEntry::make('last_used_at')
                                    ->label('最后使用时间')
                                    ->dateTime('Y-m-d H:i:s')
                                    ->placeholder('从未使用'),
                                TextEntry::make('created_at')
                                    ->label('创建时间')
                                    ->dateTime('Y-m-d H:i:s'),
                                TextEntry::make('updated_at')
                                    ->label('更新时间')
                                    ->dateTime('Y-m-d H:i:s'),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),

                // 第二行：允许的模型 + 权限配置
                Grid::make(2)
                    ->schema([
                        Section::make('允许的模型')
                            ->schema([
                                TextEntry::make('allowed_models')
                                    ->label('')
                                    ->formatStateUsing(function ($state) {
                                        if (empty($state)) {
                                            return '无限制';
                                        }
                                        if (is_array($state)) {
                                            return implode(', ', $state);
                                        }
                                        if (is_string($state)) {
                                            $decoded = json_decode($state, true);

                                            return is_array($decoded) ? implode(', ', $decoded) : $state;
                                        }

                                        return '无限制';
                                    })
                                    ->placeholder('无限制'),
                            ])
                            ->columnSpan(1),

                        Section::make('权限配置')
                            ->schema([
                                TextEntry::make('permissions')
                                    ->label('')
                                    ->formatStateUsing(fn ($state) => $state
                                        ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                        : '无')
                                    ->placeholder('无'),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),

                // 第三行：限流配置
                Section::make('限流配置')
                    ->schema([
                        TextEntry::make('rate_limit')
                            ->label('')
                            ->formatStateUsing(fn ($state) => $state
                                ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                : '无')
                            ->placeholder('无'),
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
