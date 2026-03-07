<?php

namespace App\Filament\Resources\ResponseLogs\Pages;

use App\Filament\Resources\ResponseLogs\ResponseLogResource;
use Filament\Resources\Pages\ListRecords;

class ListResponseLogs extends ListRecords
{
    protected static string $resource = ResponseLogResource::class;
}
