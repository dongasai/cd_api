<?php

namespace App\Filament\Resources\SystemSettings\Pages;

use App\Filament\Resources\SystemSettings\SystemSettingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListSystemSettings extends ListRecords
{
    protected static string $resource = SystemSettingResource::class;

    protected static ?string $title = '系统配置';

    public function table(Table $table): Table
    {
        return SystemSettingResource::table($table);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
