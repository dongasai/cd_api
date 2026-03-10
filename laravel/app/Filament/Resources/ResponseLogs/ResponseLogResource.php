<?php

namespace App\Filament\Resources\ResponseLogs;

use App\Filament\Resources\ResponseLogs\Tables\ResponseLogsTable;
use App\Models\ResponseLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ResponseLogResource extends Resource
{
    protected static ?string $model = ResponseLog::class;

    protected static ?string $modelLabel = '响应日志';

    protected static ?string $pluralModelLabel = '响应日志';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-on-square';

    protected static string|UnitEnum|null $navigationGroup = '日志管理';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 响应日志为只读，不需要表单
            ]);
    }

    public static function table(Table $table): Table
    {
        return ResponseLogsTable::configure($table);
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
            'index' => Pages\ListResponseLogs::route('/'),
            'view' => Pages\ViewResponseLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
