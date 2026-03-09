<?php

namespace App\Filament\Resources\ChannelAffinityRules\Pages;

use App\Filament\Resources\ChannelAffinityRules\ChannelAffinityRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChannelAffinityRule extends EditRecord
{
    protected static string $resource = ChannelAffinityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
