<?php

namespace App\Filament\Resources\Channels\Schemas;

use App\Models\Channel;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('基础信息')
                            ->schema([
                                Section::make('基本信息')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('渠道名称')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', \Str::slug($state))),

                                                TextInput::make('slug')
                                                    ->label('渠道标识')
                                                    ->maxLength(100)
                                                    ->unique(Channel::class, 'slug', ignoreRecord: true),

                                                Select::make('provider')
                                                    ->label('提供商类型')
                                                    ->required()
                                                    ->options([
                                                        'openai' => 'OpenAI',
                                                        'anthropic' => 'Anthropic',
                                                        'google' => 'Google AI',
                                                        'azure' => 'Azure OpenAI',
                                                        'deepseek' => 'DeepSeek',
                                                        'zhipu' => '智谱 AI',
                                                        'baidu' => '百度文心',
                                                        'alibaba' => '阿里通义',
                                                        'custom' => '自定义',
                                                    ])
                                                    ->searchable(),

                                                Select::make('status')
                                                    ->label('运营状态')
                                                    ->required()
                                                    ->options([
                                                        'active' => '启用',
                                                        'disabled' => '禁用',
                                                        'maintenance' => '维护中',
                                                    ])
                                                    ->default('active'),

                                                Textarea::make('description')
                                                    ->label('渠道描述')
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columns(2),

                                Section::make('继承配置')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('parent_id')
                                                    ->label('父渠道')
                                                    ->options(Channel::whereNull('parent_id')->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->placeholder('无'),

                                                Select::make('inherit_mode')
                                                    ->label('继承模式')
                                                    ->options([
                                                        'merge' => '合并 (与父渠道配置合并)',
                                                        'override' => '覆盖 (使用自身配置)',
                                                        'extend' => '扩展 (继承并追加)',
                                                    ])
                                                    ->default('merge'),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('连接配置')
                            ->schema([
                                Section::make('API 配置')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('base_url')
                                                    ->label('API 基础 URL')
                                                    ->url()
                                                    ->maxLength(500)
                                                    ->placeholder('https://api.openai.com/v1'),

                                                TextInput::make('api_key')
                                                    ->label('API Key')
                                                    ->password()
                                                    ->maxLength(255)
                                                    ->dehydrated(fn ($state) => filled($state))
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        if (filled($state)) {
                                                            $set('api_key_hash', substr(hash('sha256', $state), 0, 8));
                                                        }
                                                    }),
                                            ]),
                                    ]),

                                Section::make('请求头转发')
                                    ->schema([
                                        TagsInput::make('forward_headers.headers')
                                            ->label('转发的请求头')
                                            ->placeholder('输入header名称，如 x-* 或 user-agent')
                                            ->default(['x-*', 'user-agent'])
                                            ->splitKeys([',', 'Tab', ' '])
                                            ->helperText('支持通配符: x-* 匹配所有以 x- 开头的header，*agent 匹配以 agent 结尾的header')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('模型配置')
                                    ->schema([
                                        Repeater::make('channelModels')
                                            ->label('支持的模型')
                                            ->relationship('channelModels')
                                            ->addActionLabel('添加模型')
                                            ->reorderable()
                                            ->collapsible()
                                            ->schema([
                                                Grid::make(3)
                                                    ->schema([
                                                        TextInput::make('model_name')
                                                            ->label('模型名称')
                                                            ->required()
                                                            ->placeholder('如: gpt-4')
                                                            ->columnSpan(1),

                                                        TextInput::make('display_name')
                                                            ->label('显示名称')
                                                            ->placeholder('如: GPT-4')
                                                            ->columnSpan(1),

                                                        TextInput::make('mapped_model')
                                                            ->label('映射模型')
                                                            ->placeholder('渠道实际使用的模型名称')
                                                            ->columnSpan(1),
                                                    ]),

                                                Grid::make(4)
                                                    ->schema([
                                                        Toggle::make('is_default')
                                                            ->label('默认模型')
                                                            ->helperText('只能设置一个默认模型')
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                                if ($state) {
                                                                    // 获取当前 repeater 的所有项
                                                                    $items = $get('../../') ?? [];
                                                                    $currentItem = $get('../');

                                                                    // 找到当前项的索引
                                                                    $currentKey = null;
                                                                    foreach ($items as $key => $item) {
                                                                        if ($item === $currentItem) {
                                                                            $currentKey = $key;
                                                                            break;
                                                                        }
                                                                    }

                                                                    // 取消其他项的默认状态
                                                                    if ($currentKey !== null) {
                                                                        foreach ($items as $key => $item) {
                                                                            if ($key !== $currentKey && ! empty($item['is_default'])) {
                                                                                $set("../../{$key}.is_default", false);
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }),

                                                        Toggle::make('is_enabled')
                                                            ->label('启用')
                                                            ->default(true),

                                                        TextInput::make('rpm_limit')
                                                            ->label('RPM 限制')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->placeholder('无限制'),

                                                        TextInput::make('context_length')
                                                            ->label('上下文长度')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->placeholder('如: 8192'),
                                                    ]),

                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('multiplier')
                                                            ->label('消耗倍率')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue(9999.9999)
                                                            ->default(1.0000)
                                                            ->step(0.0001)
                                                            ->helperText('默认 1.00，用于调整消耗计算'),
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('负载均衡')
                            ->schema([
                                Section::make('负载均衡配置')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('weight')
                                                    ->label('权重')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(100)
                                                    ->default(1)
                                                    ->helperText('1-100，权重越高越优先'),

                                                TextInput::make('priority')
                                                    ->label('优先级')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->default(1)
                                                    ->helperText('越小越优先'),
                                            ]),
                                    ]),

                                Section::make('高级配置')
                                    ->schema([
                                        KeyValue::make('config')
                                            ->label('额外配置')
                                            ->keyLabel('配置项')
                                            ->valueLabel('配置值')
                                            ->addActionLabel('添加配置')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('统计信息')
                            ->schema([
                                Section::make('健康状态')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Select::make('health_status')
                                                    ->label('健康状态')
                                                    ->options([
                                                        'healthy' => '健康',
                                                        'unhealthy' => '不健康',
                                                        'unknown' => '未知',
                                                    ])
                                                    ->default('unknown'),

                                                TextInput::make('failure_count')
                                                    ->label('连续失败次数')
                                                    ->numeric()
                                                    ->disabled(),

                                                TextInput::make('success_count')
                                                    ->label('连续成功次数')
                                                    ->numeric()
                                                    ->disabled(),
                                            ]),
                                    ]),

                                Section::make('统计数据')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextInput::make('total_requests')
                                                    ->label('总请求数')
                                                    ->numeric()
                                                    ->disabled(),

                                                TextInput::make('total_tokens')
                                                    ->label('总 Token 数')
                                                    ->numeric()
                                                    ->disabled(),

                                                TextInput::make('total_cost')
                                                    ->label('总成本')
                                                    ->numeric()
                                                    ->disabled(),

                                                TextInput::make('avg_latency_ms')
                                                    ->label('平均延迟(ms)')
                                                    ->numeric()
                                                    ->disabled(),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
