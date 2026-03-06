<?php

namespace App\Filament\Resources\ApiKeys\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApiKeysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('key_prefix')
                    ->label('密钥前缀')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? $state . '...' : '-'),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '启用',
                        'revoked' => '已撤销',
                        'expired' => '已过期',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'active',
                        'danger' => 'revoked',
                        'warning' => 'expired',
                    ]),

                TextColumn::make('expires_at')
                    ->label('过期时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i') : '永不过期'),

                TextColumn::make('last_used_at')
                    ->label('最后使用')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i') : '从未使用'),

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
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '启用',
                        'revoked' => '已撤销',
                        'expired' => '已过期',
                    ]),
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
