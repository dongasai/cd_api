<?php

namespace App\Filament\Resources\ModelLists\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
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

                TextColumn::make('capabilities')
                    ->label('能力')
                    ->badge()
                    ->formatStateUsing(function (?array $state): string {
                        if (empty($state)) {
                            return '-';
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
                            ->join(', ');
                    })
                    ->toggleable(),

                TextColumn::make('context_length')
                    ->label('上下文长度')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) : '-')
                    ->sortable(),

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
            ->headerActions([
                CreateAction::make(),
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
