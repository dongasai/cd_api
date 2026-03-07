<?php

namespace App\Filament\Resources\RequestLogs;

use App\Filament\Resources\RequestLogs\Tables\RequestLogsTable;
use App\Models\RequestLog;
use BackedEnum;
use Filament\Resources\Resource;
use UnitEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RequestLogResource extends Resource
{
    protected static ?string $model = RequestLog::class;

    protected static ?string $modelLabel = '请求日志';

    protected static ?string $pluralModelLabel = '请求日志';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-on-square';

    protected static string|UnitEnum|null $navigationGroup = '日志管理';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 请求日志为只读，不需要表单
            ]);
    }

    public static function table(Table $table): Table
    {
        return RequestLogsTable::configure($table);
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
            'index' => Pages\ListRequestLogs::route('/'),
            'view' => Pages\ViewRequestLog::route('/{record}'),
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
