<?php

namespace App\Filament\Resources\ModelMappings\Pages;

use App\Filament\Resources\ModelMappings\ModelMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModelMappings extends ListRecords
{
    protected static string $resource = ModelMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
