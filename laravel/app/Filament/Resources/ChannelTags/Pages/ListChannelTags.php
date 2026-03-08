<?php

namespace App\Filament\Resources\ChannelTags\Pages;

use App\Filament\Resources\ChannelTags\ChannelTagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChannelTags extends ListRecords
{
    protected static string $resource = ChannelTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
