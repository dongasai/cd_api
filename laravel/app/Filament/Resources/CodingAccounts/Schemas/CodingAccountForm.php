<?php

namespace App\Filament\Resources\CodingAccounts\Schemas;

use App\Models\CodingAccount;
use App\Services\CodingStatus\CodingStatusDriverManager;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CodingAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基本信息')
                    ->schema([
                        TextInput::make('name')
                            ->label('账户名称')
                            ->required()
                            ->maxLength(255),

                        Select::make('platform')
                            ->label('平台类型')
                            ->options(CodingAccount::getPlatforms())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $manager = app(CodingStatusDriverManager::class);
                                $recommendedDrivers = $manager->getRecommendedDriversForPlatform($state);
                                if (!empty($recommendedDrivers)) {
                                    $set('driver_class', $recommendedDrivers[0]);
                                }
                            }),

                        Select::make('driver_class')
                            ->label('驱动类型')
                            ->options(function () {
                                $manager = app(CodingStatusDriverManager::class);
                                return $manager->getDriverOptions();
                            })
                            ->required()
                            ->live(),

                        Select::make('status')
                            ->label('账户状态')
                            ->options(CodingAccount::getStatuses())
                            ->default(CodingAccount::STATUS_ACTIVE)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('凭证信息')
                    ->schema([
                        KeyValue::make('credentials')
                            ->label('API凭证')
                            ->keyLabel('键名')
                            ->valueLabel('值')
                            ->helperText('例如: api_key, api_secret, access_token')
                            ->default([
                                'api_key' => '',
                            ]),
                    ]),

                Section::make('配额配置')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                KeyValue::make('quota_config.limits')
                                    ->label('配额限制')
                                    ->keyLabel('维度')
                                    ->valueLabel('限制值')
                                    ->helperText('例如: tokens_input, tokens_output, requests, prompts'),

                                KeyValue::make('quota_config.thresholds')
                                    ->label('阈值配置')
                                    ->keyLabel('阈值类型')
                                    ->valueLabel('值 (0-1)')
                                    ->default([
                                        'warning' => '0.80',
                                        'critical' => '0.90',
                                        'disable' => '0.95',
                                    ]),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('quota_config.cycle')
                                    ->label('重置周期')
                                    ->options([
                                        '5h' => '5小时',
                                        'daily' => '每日',
                                        'weekly' => '每周',
                                        'monthly' => '每月',
                                    ])
                                    ->default('monthly'),

                                TextInput::make('quota_config.reset_day')
                                    ->label('重置日期')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->default(1)
                                    ->helperText('每月重置日期(1-31)'),
                            ]),
                    ]),

                Section::make('扩展配置')
                    ->schema([
                        KeyValue::make('config')
                            ->label('驱动特定配置')
                            ->keyLabel('键名')
                            ->valueLabel('值'),
                    ]),

                Section::make('过期时间')
                    ->schema([
                        DateTimePicker::make('expires_at')
                            ->label('账户过期时间')
                            ->nullable(),
                    ]),
            ]);
    }
}
