<?php

namespace App\Filament\Resources\ApiKeys\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApiKeyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基本信息')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('名称')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('用于标识此 API Key'),

                                Select::make('status')
                                    ->label('状态')
                                    ->required()
                                    ->options([
                                        'active' => '启用',
                                        'revoked' => '已撤销',
                                        'expired' => '已过期',
                                    ])
                                    ->default('active'),
                            ]),
                    ]),

                Section::make('密钥信息')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('key_prefix')
                                    ->label('密钥前缀')
                                    ->maxLength(20)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visibleOn('edit')
                                    ->helperText('密钥前缀（创建时自动生成）'),

                                DateTimePicker::make('expires_at')
                                    ->label('过期时间')
                                    ->placeholder('选择过期时间')
                                    ->helperText('留空表示永不过期'),
                            ]),
                    ]),

                Section::make('权限配置')
                    ->schema([
                        KeyValue::make('permissions')
                            ->label('权限列表')
                            ->keyLabel('权限标识')
                            ->valueLabel('权限值')
                            ->addActionLabel('添加权限')
                            ->columnSpanFull(),
                    ]),

                Section::make('模型配置')
                    ->schema([
                        KeyValue::make('allowed_models')
                            ->label('允许的模型')
                            ->keyLabel('模型标识')
                            ->valueLabel('模型名称')
                            ->addActionLabel('添加模型')
                            ->columnSpanFull(),
                    ]),

                Section::make('限流配置')
                    ->schema([
                        KeyValue::make('rate_limit')
                            ->label('限流规则')
                            ->keyLabel('规则名称')
                            ->valueLabel('规则值')
                            ->addActionLabel('添加规则')
                            ->helperText('如: requests_per_minute => 60')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
