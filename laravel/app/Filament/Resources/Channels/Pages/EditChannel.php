<?php

namespace App\Filament\Resources\Channels\Pages;

use App\Filament\Resources\Channels\ChannelResource;
use Filament\Resources\Pages\EditRecord;

class EditChannel extends EditRecord
{
    protected static string $resource = ChannelResource::class;

    protected function afterSave(): void
    {
        // 确保数据库中只有一个默认模型
        $this->ensureSingleDefaultModel($this->record);
    }

    /**
     * 确保只有一个默认模型
     */
    protected function ensureSingleDefaultModel($record): void
    {
        $defaultModels = $record->channelModels()->where('is_default', true)->get();

        if ($defaultModels->count() > 1) {
            // 保留第一个，其他的取消默认
            $first = true;
            foreach ($defaultModels as $model) {
                if ($first) {
                    $first = false;
                } else {
                    $model->update(['is_default' => false]);
                }
            }
        }
    }
}
