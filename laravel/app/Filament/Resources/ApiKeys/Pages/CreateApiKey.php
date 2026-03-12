<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Filament\Resources\ApiKeys\ApiKeyResource;
use App\Models\SystemSetting;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $prefix = SystemSetting::getValue(SystemSetting::GROUP_SECURITY, 'api_key_prefix', 'sk-');
        $keyLength = SystemSetting::getValue(SystemSetting::GROUP_SECURITY, 'key_length', 48);
        $plainKey = $prefix.Str::random($keyLength);

        $data['key'] = $plainKey;
        $data['key_hash'] = hash('sha256', $plainKey);
        $data['key_prefix'] = substr($plainKey, 0, strlen($prefix) + 4);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
