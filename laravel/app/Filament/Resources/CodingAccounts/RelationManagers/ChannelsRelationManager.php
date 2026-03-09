<?php

namespace App\Filament\Resources\CodingAccounts\RelationManagers;

use App\Filament\Resources\Channels\ChannelResource;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChannelsRelationManager extends RelationManager
{
    protected static string $relationship = 'channels';

    protected static ?string $title = '关联渠道';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('渠道名称')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('渠道标识')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('运营状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'disabled' => 'danger',
                        'maintenance' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '启用',
                        'disabled' => '禁用',
                        'maintenance' => '维护中',
                        default => $state,
                    }),

                TextColumn::make('provider')
                    ->label('提供商')
                    ->searchable(),

                TextColumn::make('priority')
                    ->label('优先级')
                    ->sortable(),

                TextColumn::make('weight')
                    ->label('权重')
                    ->sortable(),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label('关联渠道')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->whereNull('coding_account_id')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => ChannelResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->url(fn ($record) => ChannelResource::getUrl('edit', ['record' => $record])),
                DissociateAction::make()
                    ->label('取消关联'),
            ])
            ->toolbarActions([
                DissociateBulkAction::make()
                    ->label('批量取消关联'),
            ]);
    }
}
