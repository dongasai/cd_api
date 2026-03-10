<?php

namespace App\Filament\Resources\ChannelGroups;

use App\Filament\Resources\ChannelGroups\Schemas\ChannelGroupForm;
use App\Filament\Resources\ChannelGroups\Tables\ChannelGroupsTable;
use App\Models\ChannelGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ChannelGroupResource extends Resource
{
    protected static ?string $model = ChannelGroup::class;

    protected static ?string $modelLabel = '渠道分组';

    protected static ?string $pluralModelLabel = '渠道分组';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|UnitEnum|null $navigationGroup = '渠道管理';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ChannelGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChannelGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChannelGroups::route('/'),
            'create' => Pages\CreateChannelGroup::route('/create'),
            'edit' => Pages\EditChannelGroup::route('/{record}/edit'),
        ];
    }
}
