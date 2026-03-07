<?php

namespace App\Filament\Resources\ModelMappings\Pages;

use App\Filament\Resources\ModelMappings\ModelMappingResource;
use Filament\Resources\Pages\EditRecord;

class EditModelMapping extends EditRecord
{
    protected static string $resource = ModelMappingResource::class;
}
