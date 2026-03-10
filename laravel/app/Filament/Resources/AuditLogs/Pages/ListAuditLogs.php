<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    // 设置页面内容宽度为全宽
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
