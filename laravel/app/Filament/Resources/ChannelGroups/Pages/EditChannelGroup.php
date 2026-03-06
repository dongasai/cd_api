<?php

namespace App\Filament\Resources\ChannelGroups\Pages;

use App\Filament\Resources\ChannelGroups\ChannelGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChannelGroup extends EditRecord
{
    protected static string $resource = ChannelGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
