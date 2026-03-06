<?php

namespace App\Filament\Resources\CodingAccountResource\Pages;

use App\Filament\Resources\CodingAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCodingAccount extends EditRecord
{
    protected static string $resource = CodingAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
