<?php

namespace App\Filament\Resources\CodingAccounts\Pages;

use App\Filament\Resources\CodingAccounts\CodingAccountResource;
use Filament\Resources\Pages\EditRecord;

class EditCodingAccount extends EditRecord
{
    protected static string $resource = CodingAccountResource::class;

    public function getMaxContentWidth(): string
    {
        return 'full';
    }
}
