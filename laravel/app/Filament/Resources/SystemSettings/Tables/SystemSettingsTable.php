<?php

namespace App\Filament\Resources\SystemSettings\Tables;

use App\Models\SystemSetting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class SystemSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')
                    ->label('分组')
                    ->formatStateUsing(fn (string $state): string => SystemSetting::getGroups()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SystemSetting::GROUP_SYSTEM => 'primary',
                        SystemSetting::GROUP_QUOTA => 'warning',
                        SystemSetting::GROUP_SECURITY => 'danger',
                        SystemSetting::GROUP_FEATURES => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('key')
                    ->label('配置键')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('label')
                    ->label('标签')
                    ->searchable(),

                Tables\Columns\TextColumn::make('value')
                    ->label('值')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->value)
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->type === SystemSetting::TYPE_BOOLEAN) {
                            return $state === '1' ? '✓ 是' : '✗ 否';
                        }
                        if (in_array($record->type, [SystemSetting::TYPE_JSON, SystemSetting::TYPE_ARRAY])) {
                            $decoded = json_decode($state, true);
                            if (is_array($decoded)) {
                                return json_encode($decoded, JSON_UNESCAPED_UNICODE);
                            }
                        }

                        return $state;
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->label('类型')
                    ->formatStateUsing(fn (string $state): string => SystemSetting::getTypes()[$state] ?? $state)
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('公开')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('分组')
                    ->options(SystemSetting::getGroups()),

                Tables\Filters\SelectFilter::make('type')
                    ->label('类型')
                    ->options(SystemSetting::getTypes()),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('是否公开'),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
