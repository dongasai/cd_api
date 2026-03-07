<?php

namespace App\Filament\Resources\ModelMappings\Pages;

use App\Filament\Resources\ModelMappings\ModelMappingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModelMapping extends CreateRecord
{
    protected static string $resource = ModelMappingResource::class;
}
