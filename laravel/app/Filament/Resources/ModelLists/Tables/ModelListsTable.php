<?php

namespace App\Filament\Resources\ModelLists\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModelListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('model_name')
                    ->label('模型名称')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('display_name')
                    ->label('显示名称')
                    ->searchable()
                    ->default('-'),

                TextColumn::make('provider')
                    ->label('提供商')
                    ->searchable()
                    ->default('-'),

                TextColumn::make('common_name')
                    ->label('通用名字')
                    ->searchable()
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('hugging_face_id')
                    ->label('Hugging Face ID')
                    ->searchable()
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('capabilities')
                    ->label('能力')
                    ->formatStateUsing(function ($state): string {
                        if (empty($state)) {
                            return '';
                        }
                        $labels = [
                            'reasoning' => '推理',
                            'text' => '文本',
                            'image' => '图片',
                            'audio' => '语音',
                            'video' => '视频',
                            'tool_call' => '工具调用',
                            'web_search' => '联网',
                        ];

                        return collect($state)
                            ->map(fn ($item) => $labels[$item] ?? $item)
                            ->join(',');
                    })
                    ->badge()
                    ->separator(',')
                    ->toggleable(),

                TextColumn::make('context_length')
                    ->label('上下文长度')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) : '-')
                    ->sortable(),

                TextColumn::make('pricing_prompt')
                    ->label('输入价格')
                    ->money('USD', divideBy: 1000000)
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pricing_completion')
                    ->label('输出价格')
                    ->money('USD', divideBy: 1000000)
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('pricing_input_cache_read')
                    ->label('缓存价格')
                    ->money('USD', divideBy: 1000000)
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_enabled')
                    ->label('状态')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
}
