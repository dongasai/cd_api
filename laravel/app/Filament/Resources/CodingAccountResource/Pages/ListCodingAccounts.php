<?php

namespace App\Filament\Resources\CodingAccountResource\Pages;

use App\Filament\Resources\CodingAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCodingAccounts extends ListRecords
{
    protected static string $resource = CodingAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
