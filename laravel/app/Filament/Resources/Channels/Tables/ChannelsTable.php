<?php

namespace App\Filament\Resources\Channels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('渠道名称')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('标识')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('provider')
                    ->label('提供商')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'openai' => 'OpenAI',
                        'anthropic' => 'Anthropic',
                        'google' => 'Google AI',
                        'azure' => 'Azure OpenAI',
                        'deepseek' => 'DeepSeek',
                        'zhipu' => '智谱 AI',
                        'baidu' => '百度文心',
                        'alibaba' => '阿里通义',
                        'custom' => '自定义',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'openai',
                        'warning' => 'anthropic',
                        'danger' => 'google',
                        'info' => 'azure',
                    ]),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '启用',
                        'disabled' => '禁用',
                        'maintenance' => '维护中',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'active',
                        'danger' => 'disabled',
                        'warning' => 'maintenance',
                    ]),

                TextColumn::make('health_status')
                    ->label('健康')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'healthy' => '健康',
                        'unhealthy' => '不健康',
                        'unknown' => '未知',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'healthy',
                        'danger' => 'unhealthy',
                        'gray' => 'unknown',
                    ]),

                TextColumn::make('groups.name')
                    ->label('分组')
                    ->badge()
                    ->limitList(3),

                TextColumn::make('priority')
                    ->label('优先级')
                    ->sortable(),

                TextColumn::make('weight')
                    ->label('权重')
                    ->sortable(),

                TextColumn::make('total_requests')
                    ->label('请求数')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state)),

                TextColumn::make('success_rate')
                    ->label('成功率')
                    ->formatStateUsing(fn ($state) => ($state * 100).'%')
                    ->color(fn ($state) => $state >= 0.9 ? 'success' : ($state >= 0.7 ? 'warning' : 'danger')),

                TextColumn::make('parent.name')
                    ->label('父渠道')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label('提供商')
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
                    ]),

                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '启用',
                        'disabled' => '禁用',
                        'maintenance' => '维护中',
                    ]),

                SelectFilter::make('health_status')
                    ->label('健康状态')
                    ->options([
                        'healthy' => '健康',
                        'unhealthy' => '不健康',
                        'unknown' => '未知',
                    ]),
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
            ->defaultSort('priority', 'asc');
    }
}
