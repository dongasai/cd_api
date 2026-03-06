<?php

namespace App\Filament\Resources\CodingAccountResource\Pages;

use App\Filament\Resources\CodingAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCodingAccount extends CreateRecord
{
    protected static string $resource = CodingAccountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
