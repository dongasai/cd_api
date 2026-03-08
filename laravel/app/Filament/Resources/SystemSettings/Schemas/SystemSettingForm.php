<?php

namespace App\Filament\Resources\SystemSettings\Schemas;

use App\Models\SystemSetting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SystemSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('配置信息')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('group')
                                    ->label('配置分组')
                                    ->options(SystemSetting::getGroups())
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('key')
                                    ->label('配置键')
                                    ->required()
                                    ->maxLength(100)
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('label')
                                    ->label('显示标签')
                                    ->required()
                                    ->maxLength(100),

                                Select::make('type')
                                    ->label('值类型')
                                    ->options(SystemSetting::getTypes())
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('sort_order')
                                    ->label('排序')
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_public')
                                    ->label('公开给前端')
                                    ->helperText('公开的配置可以被前端API获取'),
                            ]),

                        Textarea::make('description')
                            ->label('配置说明')
                            ->rows(2)
                            ->columnSpanFull(),

                        self::getValueComponent(),
                    ]),
            ]);
    }

    private static function getValueComponent()
    {
        return TextInput::make('value')
            ->label('配置值')
            ->required()
            ->afterStateHydrated(function ($component, $state, $record) {
                if ($record) {
                    $component->label('配置值 ('.$record->getTypeLabel().')');
                }
            });
    }
}
