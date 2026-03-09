<?php

namespace App\Filament\Resources\CodingAccounts\Tables;

use App\Models\CodingAccount;
use App\Services\CodingStatus\CodingStatusDriverManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CodingAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('账户名称')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('platform')
                    ->label('平台')
                    ->formatStateUsing(fn (string $state): string => CodingAccount::getPlatforms()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CodingAccount::PLATFORM_ALIYUN => 'info',
                        CodingAccount::PLATFORM_VOLCANO => 'danger',
                        CodingAccount::PLATFORM_ZHIPU => 'success',
                        CodingAccount::PLATFORM_GITHUB => 'gray',
                        CodingAccount::PLATFORM_CURSOR => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('driver_class')
                    ->label('驱动类型')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CodingAccount::STATUS_ACTIVE => 'success',
                        CodingAccount::STATUS_WARNING => 'warning',
                        CodingAccount::STATUS_CRITICAL => 'danger',
                        CodingAccount::STATUS_EXHAUSTED => 'gray',
                        CodingAccount::STATUS_EXPIRED => 'gray',
                        CodingAccount::STATUS_SUSPENDED => 'gray',
                        CodingAccount::STATUS_ERROR => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => CodingAccount::getStatuses()[$state] ?? $state)
                    ->sortable(),

                TextColumn::make('last_sync_at')
                    ->label('最后同步')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('未同步')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('过期时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('永不过期')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label('平台')
                    ->options(CodingAccount::getPlatforms()),

                SelectFilter::make('status')
                    ->label('状态')
                    ->options(CodingAccount::getStatuses()),

                SelectFilter::make('driver_class')
                    ->label('驱动类型')
                    ->options(function () {
                        $manager = app(CodingStatusDriverManager::class);

                        return $manager->getDriverOptions();
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ViewAction::make(),
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
