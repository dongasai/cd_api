<?php

namespace App\Filament\Resources\ModelMappings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ModelMappingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('alias')
                    ->label('模型别名')
                    ->searchable()
                    ->sortable()
                    ->weight('font-bold'),

                TextColumn::make('actual_model')
                    ->label('实际模型')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('channel.name')
                    ->label('默认渠道')
                    ->placeholder('未指定')
                    ->sortable(),

                IconColumn::make('enabled')
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
            ->filters([
                TernaryFilter::make('enabled')
                    ->label('启用状态')
                    ->placeholder('全部')
                    ->trueLabel('已启用')
                    ->falseLabel('已禁用'),

                SelectFilter::make('channel_id')
                    ->label('默认渠道')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload(),
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
            ->defaultSort('created_at', 'desc');
    }
}
