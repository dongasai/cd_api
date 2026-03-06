<?php

namespace App\Filament\Resources\ChannelTags\Schemas;

use App\Models\ChannelTag;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChannelTagForm
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
                                    ->label('标签名称')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ChannelTag::class, 'name', ignoreRecord: true),

                                ColorPicker::make('color')
                                    ->label('标签颜色')
                                    ->default('#666666'),

                                TextInput::make('description')
                                    ->label('标签描述')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('渠道关联')
                    ->schema([
                        Select::make('channels')
                            ->label('选择渠道')
                            ->relationship('channels', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('选择拥有此标签的渠道'),
                    ]),
            ]);
    }
}
