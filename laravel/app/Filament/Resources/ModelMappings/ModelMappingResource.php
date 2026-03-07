<?php

namespace App\Filament\Resources\ModelMappings;

use App\Filament\Resources\ModelMappings\Schemas\ModelMappingForm;
use App\Filament\Resources\ModelMappings\Tables\ModelMappingsTable;
use App\Models\ModelMapping;
use BackedEnum;
use Filament\Resources\Resource;
use UnitEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ModelMappingResource extends Resource
{
    protected static ?string $model = ModelMapping::class;

    protected static ?string $modelLabel = '模型映射';

    protected static ?string $pluralModelLabel = '模型映射';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = '渠道管理';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return ModelMappingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModelMappingsTable::configure($table);
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
            'index' => Pages\ListModelMappings::route('/'),
            'create' => Pages\CreateModelMapping::route('/create'),
            'edit' => Pages\EditModelMapping::route('/{record}/edit'),
        ];
    }
}
