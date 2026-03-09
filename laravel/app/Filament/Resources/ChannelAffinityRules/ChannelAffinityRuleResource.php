<?php

namespace App\Filament\Resources\ChannelAffinityRules;

use App\Filament\Resources\ChannelAffinityRules\Pages\CreateChannelAffinityRule;
use App\Filament\Resources\ChannelAffinityRules\Pages\EditChannelAffinityRule;
use App\Filament\Resources\ChannelAffinityRules\Pages\ListChannelAffinityRules;
use App\Models\ChannelAffinityRule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ChannelAffinityRuleResource extends Resource
{
    protected static ?string $model = ChannelAffinityRule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = '渠道亲和性规则';

    protected static ?string $pluralModelLabel = '渠道亲和性规则';

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                Section::make('基本信息')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label('规则名称')
                                    ->required()
                                    ->maxLength(100),
                                Toggle::make('is_enabled')
                                    ->label('启用')
                                    ->default(true),
                                TextInput::make('priority')
                                    ->label('优先级')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('数值越大越优先'),
                            ]),
                        Textarea::make('description')
                            ->label('规则描述')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('匹配条件')
                    ->schema([
                        KeyValue::make('model_patterns')
                            ->label('模型匹配正则')
                            ->keyLabel('正则表达式')
                            ->valueLabel('说明')
                            ->helperText('如: ^gpt-.*$, ^claude-.*$')
                            ->columnSpanFull(),
                        KeyValue::make('path_patterns')
                            ->label('路径匹配正则')
                            ->keyLabel('正则表达式')
                            ->valueLabel('说明')
                            ->helperText('如: /v1/chat/completions')
                            ->columnSpanFull(),
                        KeyValue::make('user_agent_patterns')
                            ->label('User-Agent 匹配')
                            ->keyLabel('包含字符串')
                            ->valueLabel('说明')
                            ->helperText('如: RooCode, Claude')
                            ->columnSpanFull(),
                    ]),

                Section::make('Key 提取配置')
                    ->schema([
                        Repeater::make('key_sources')
                            ->label('Key 来源')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        Select::make('type')
                                            ->label('类型')
                                            ->options([
                                                'header' => '请求头',
                                                'json_path' => 'JSON Path',
                                                'query' => '查询参数',
                                                'api_key' => 'API Key',
                                                'client_ip' => '客户端 IP',
                                                'user_agent' => 'User-Agent',
                                            ])
                                            ->required()
                                            ->live()
                                            ->columnSpan(1),
                                        TextInput::make('key')
                                            ->label('Key 名称')
                                            ->visible(fn ($get) => in_array($get('type'), ['header', 'query']))
                                            ->columnSpan(1),
                                        TextInput::make('path')
                                            ->label('JSON Path')
                                            ->visible(fn ($get) => $get('type') === 'json_path')
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->addActionLabel('添加 Key 来源')
                            ->columnSpanFull(),
                        Select::make('key_combine_strategy')
                            ->label('组合策略')
                            ->options([
                                'first' => '使用第一个非空值',
                                'concat' => '拼接所有值',
                                'hash' => '哈希组合值',
                            ])
                            ->default('first')
                            ->helperText('当有多个 Key 来源时的组合方式')
                            ->columnSpanFull(),
                    ]),

                Section::make('高级配置')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('ttl_seconds')
                                    ->label('缓存 TTL')
                                    ->numeric()
                                    ->default(120)
                                    ->suffix('秒'),
                                Toggle::make('skip_retry_on_failure')
                                    ->label('失败后跳过重试')
                                    ->helperText('启用后请求失败不会尝试其他渠道'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Toggle::make('include_group_in_key')
                                    ->label('缓存 Key 包含分组')
                                    ->helperText('不同分组使用不同的亲和渠道'),
                                Textarea::make('param_override_template')
                                    ->label('参数覆盖模板 (JSON)')
                                    ->rows(3)
                                    ->helperText('命中规则后自动合并的参数'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('规则名称')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_enabled')
                    ->label('启用')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('优先级')
                    ->sortable(),
                TextColumn::make('hit_count')
                    ->label('命中次数')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state)),
                TextColumn::make('last_hit_at')
                    ->label('最后命中')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_enabled')
                    ->label('启用状态'),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChannelAffinityRules::route('/'),
            'create' => CreateChannelAffinityRule::route('/create'),
            'edit' => EditChannelAffinityRule::route('/{record}/edit'),
        ];
    }
}
