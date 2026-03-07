<?php

namespace App\Filament\Resources\ChannelTags;

use App\Filament\Resources\ChannelTags\Schemas\ChannelTagForm;
use App\Filament\Resources\ChannelTags\Tables\ChannelTagsTable;
use App\Models\ChannelTag;
use BackedEnum;
use Filament\Resources\Resource;
use UnitEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ChannelTagResource extends Resource
{
    protected static ?string $model = ChannelTag::class;

    protected static ?string $modelLabel = '渠道标签';

    protected static ?string $pluralModelLabel = '渠道标签';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = '渠道管理';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return ChannelTagForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChannelTagsTable::configure($table);
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
            'index' => Pages\ListChannelTags::route('/'),
            'create' => Pages\CreateChannelTag::route('/create'),
            'edit' => Pages\EditChannelTag::route('/{record}/edit'),
        ];
    }
}
