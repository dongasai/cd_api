<?php

namespace App\Filament\Resources\ChannelRequestLogs;

use App\Filament\Resources\ChannelRequestLogs\Tables\ChannelRequestLogsTable;
use App\Models\ChannelRequestLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ChannelRequestLogResource extends Resource
{
    protected static ?string $model = ChannelRequestLog::class;

    protected static ?string $modelLabel = '渠道请求日志';

    protected static ?string $pluralModelLabel = '渠道请求日志';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|UnitEnum|null $navigationGroup = '日志管理';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 渠道请求日志为只读，不需要表单
            ]);
    }

    public static function table(Table $table): Table
    {
        return ChannelRequestLogsTable::configure($table);
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
            'index' => Pages\ListChannelRequestLogs::route('/'),
            'view' => Pages\ViewChannelRequestLog::route('/{record}'),
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
