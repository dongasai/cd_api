<?php

namespace App\Filament\Resources\SystemSettings;

use App\Filament\Resources\SystemSettings\Schemas\SystemSettingForm;
use App\Filament\Resources\SystemSettings\Tables\SystemSettingsTable;
use App\Models\SystemSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static ?string $modelLabel = '系统配置';

    protected static ?string $pluralModelLabel = '系统配置';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = '系统配置';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SystemSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SystemSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemSettings::route('/'),
            'edit' => Pages\EditSystemSetting::route('/{record}/edit'),
        ];
    }
}
