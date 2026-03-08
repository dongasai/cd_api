<?php

namespace App\Filament\Resources\ModelLists\Schemas;

use App\Models\ModelList;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ModelListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基本信息')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('model_name')
                                    ->label('模型名称')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ModelList::class, 'model_name', ignoreRecord: true)
                                    ->placeholder('如: gpt-4, claude-3-opus'),

                                TextInput::make('display_name')
                                    ->label('显示名称')
                                    ->maxLength(100)
                                    ->placeholder('如: GPT-4, Claude 3 Opus'),

                                Select::make('provider')
                                    ->label('提供商')
                                    ->options([
                                        'openai' => 'OpenAI',
                                        'anthropic' => 'Anthropic',
                                        'google' => 'Google',
                                        'azure' => 'Azure OpenAI',
                                        'deepseek' => 'DeepSeek',
                                        'moonshot' => 'Moonshot',
                                        'zhipu' => '智谱AI',
                                        'baidu' => '百度',
                                        'alibaba' => '阿里云',
                                        'other' => '其他',
                                    ])
                                    ->searchable(),

                                TextInput::make('context_length')
                                    ->label('上下文长度')
                                    ->numeric()
                                    ->placeholder('如: 128000'),

                                Checkbox::make('is_enabled')
                                    ->label('启用')
                                    ->default(true),
                            ]),

                        Textarea::make('description')
                            ->label('描述')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('能力配置')
                    ->schema([
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

                Section::make('扩展配置')
                    ->schema([
                        KeyValue::make('config')
                            ->label('额外配置')
                            ->keyLabel('配置项')
                            ->valueLabel('配置值')
                            ->addActionLabel('添加配置')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
