<?php

namespace App\Filament\Resources\ModelLists;

use App\Filament\Resources\ModelLists\Schemas\ModelListForm;
use App\Filament\Resources\ModelLists\Tables\ModelListsTable;
use App\Models\ModelList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ModelListResource extends Resource
{
    protected static ?string $model = ModelList::class;

    protected static ?string $modelLabel = '模型';

    protected static ?string $pluralModelLabel = '模型列表';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return ModelListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModelListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModelLists::route('/'),
            'create' => Pages\CreateModelList::route('/create'),
            'edit' => Pages\EditModelList::route('/{record}/edit'),
        ];
    }
}
