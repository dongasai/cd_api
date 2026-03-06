<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CodingAccountResource\Pages;
use App\Models\CodingAccount;
use App\Services\CodingStatus\CodingStatusDriverManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\KeyValue;
use Filament\Schemas\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CodingAccountResource extends Resource
{
    protected static ?string $model = CodingAccount::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Coding账户';

    protected static ?string $modelLabel = 'Coding账户';

    protected static ?string $pluralModelLabel = 'Coding账户';

    protected static string | \UnitEnum | null $navigationGroup = '渠道管理';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
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
                                ->afterStateUpdated(function ($state, $set) {
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
                                ->helperText('每月重置的日期 (仅月度周期有效)'),
                        ])
                        ->columns(2),

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
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('账户名称')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('platform')
                    ->label('平台')
                    ->formatStateUsing(fn (string $state): string => CodingAccount::getPlatforms()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CodingAccount::PLATFORM_ALIYUN => 'info',
                        CodingAccount::PLATFORM_VOLCANO => 'danger',
                        CodingAccount::PLATFORM_ZHIPU => 'success',
                        CodingAccount::PLATFORM_GITHUB => 'gray',
                        CodingAccount::PLATFORM_CURSOR => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('driver_class')
                    ->label('驱动类型')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CodingAccount::STATUS_ACTIVE => 'success',
                        CodingAccount::STATUS_WARNING => 'warning',
                        CodingAccount::STATUS_CRITICAL => 'danger',
                        CodingAccount::STATUS_EXHAUSTED => 'gray',
                        CodingAccount::STATUS_EXPIRED => 'gray',
                        CodingAccount::STATUS_SUSPENDED => 'gray',
                        CodingAccount::STATUS_ERROR => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => CodingAccount::getStatuses()[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_sync_at')
                    ->label('最后同步')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('未同步')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('过期时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('永不过期')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->label('平台')
                    ->options(CodingAccount::getPlatforms()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options(CodingAccount::getStatuses()),

                Tables\Filters\SelectFilter::make('driver_class')
                    ->label('驱动类型')
                    ->options(function () {
                        $manager = app(CodingStatusDriverManager::class);
                        return $manager->getDriverOptions();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label('同步配额')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (Model $record) {
                        try {
                            $manager = app(CodingStatusDriverManager::class);
                            $driver = $manager->driverForAccount($record);
                            $driver->sync();

                            return redirect()->back()->with('success', '配额同步成功');
                        } catch (\Exception $e) {
                            return redirect()->back()->with('error', '同步失败: ' . $e->getMessage());
                        }
                    }),

                Tables\Actions\Action::make('validate')
                    ->label('验证凭证')
                    ->icon('heroicon-m-shield-check')
                    ->color('success')
                    ->action(function (Model $record) {
                        $manager = app(CodingStatusDriverManager::class);
                        $driver = $manager->driverForAccount($record);
                        $result = $driver->validateCredentials();

                        if ($result['valid']) {
                            return redirect()->back()->with('success', $result['message']);
                        } else {
                            return redirect()->back()->with('error', $result['message']);
                        }
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCodingAccounts::route('/'),
            'create' => Pages\CreateCodingAccount::route('/create'),
            'view' => Pages\ViewCodingAccount::route('/{record}'),
            'edit' => Pages\EditCodingAccount::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('created_at', 'desc');
    }
}
