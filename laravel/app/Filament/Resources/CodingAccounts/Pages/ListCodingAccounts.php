<?php

namespace App\Filament\Resources\CodingAccounts\Pages;

use App\Filament\Resources\CodingAccounts\CodingAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCodingAccounts extends ListRecords
{
    protected static string $resource = CodingAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
