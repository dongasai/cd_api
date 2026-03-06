<?php

namespace App\Filament\Resources\CodingAccounts\Pages;

use App\Filament\Resources\CodingAccounts\CodingAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCodingAccount extends CreateRecord
{
    protected static string $resource = CodingAccountResource::class;

    public function getMaxContentWidth(): string
    {
        return 'full';
    }
}
