<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with('requestLog');
    }
}
