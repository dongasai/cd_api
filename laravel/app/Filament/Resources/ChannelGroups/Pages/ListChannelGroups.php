<?php

namespace App\Filament\Resources\ChannelGroups\Pages;

use App\Filament\Resources\ChannelGroups\ChannelGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChannelGroups extends ListRecords
{
    protected static string $resource = ChannelGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
