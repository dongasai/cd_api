<?php

namespace App\Filament\Resources\ModelMappings\Schemas;

use App\Models\Channel;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ModelMappingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('模型映射信息')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('alias')
                                    ->label('模型别名')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('例如: gpt-4')
                                    ->helperText('对外展示的模型名称')
                                    ->unique(ignoreRecord: true),

                                TextInput::make('actual_model')
                                    ->label('实际模型')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('例如: gpt-4-turbo-preview')
                                    ->helperText('实际调用的模型名称'),

                                Select::make('channel_id')
                                    ->label('默认渠道')
                                    ->options(Channel::pluck('name', 'id'))
                                    ->searchable()
                                    ->placeholder('选择默认渠道')
                                    ->helperText('可选，指定此映射的默认渠道'),

                                Toggle::make('enabled')
                                    ->label('启用')
                                    ->default(true)
                                    ->helperText('是否启用此模型映射'),

                                CheckboxList::make('capabilities')
                                    ->label('模型能力')
                                    ->options([
                                        'reasoning' => '推理',
                                        'text' => '文本',
                                        'image' => '图片',
                                        'audio' => '语音',
                                        'video' => '视频',
                                        'tool_call' => '工具调用',
                                        'web_search' => '联网',
                                    ])
                                    ->columns(4)
                                    ->helperText('选择该模型支持的能力')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
