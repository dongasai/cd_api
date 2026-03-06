<?php

namespace App\Filament\Resources\CodingAccounts;

use App\Filament\Resources\CodingAccounts\Schemas\CodingAccountForm;
use App\Filament\Resources\CodingAccounts\Tables\CodingAccountsTable;
use App\Models\CodingAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CodingAccountResource extends Resource
{
    protected static ?string $model = CodingAccount::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Coding账户';

    protected static ?string $modelLabel = 'Coding账户';

    protected static ?string $pluralModelLabel = 'Coding账户';

    protected static string | \UnitEnum | null $navigationGroup = '渠道管理';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return CodingAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CodingAccountsTable::configure($table);
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
            'index' => Pages\ListCodingAccounts::route('/'),
            'create' => Pages\CreateCodingAccount::route('/create'),
            'view' => Pages\ViewCodingAccount::route('/{record}'),
            'edit' => Pages\EditCodingAccount::route('/{record}/edit'),
        ];
    }
}
