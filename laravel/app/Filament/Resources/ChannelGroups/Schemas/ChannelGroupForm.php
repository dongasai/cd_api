<?php

namespace App\Filament\Resources\ChannelGroups\Schemas;

use App\Models\ChannelGroup;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChannelGroupForm
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
                                    ->label('分组名称')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', \Str::slug($state))),

                                TextInput::make('slug')
                                    ->label('分组标识')
                                    ->maxLength(100)
                                    ->unique(ChannelGroup::class, 'slug', ignoreRecord: true),

                                Textarea::make('description')
                                    ->label('分组描述')
                                    ->rows(2)
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
                            ->helperText('选择属于此分组的渠道'),
                    ]),

                Section::make('高级配置')
                    ->schema([
                        KeyValue::make('config')
                            ->label('分组配置')
                            ->keyLabel('配置项')
                            ->valueLabel('配置值')
                            ->addActionLabel('添加配置')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
