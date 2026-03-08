<?php

namespace App\Filament\Resources\ModelLists\Pages;

use App\Filament\Resources\ModelLists\ModelListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModelLists extends ListRecords
{
    protected static string $resource = ModelListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
