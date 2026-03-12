<?php

namespace App\Filament\Resources\Channels\Pages;

use App\Filament\Resources\Channels\ChannelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChannel extends CreateRecord
{
    protected static string $resource = ChannelResource::class;

    /**
     * 在保存前处理表单数据
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 构建 config 数组
        $config = $data['config'] ?? [];
        if (! is_array($config)) {
            $config = [];
        }

        // 将 filter_thinking 放入 config
        $config['filter_thinking'] = $data['filter_thinking'] ?? false;

        // 合并额外配置
        if (isset($data['config_extra']) && is_array($data['config_extra'])) {
            foreach ($data['config_extra'] as $key => $value) {
                $config[$key] = $value;
            }
        }

        $data['config'] = $config;
        unset($data['filter_thinking'], $data['config_extra']);

        return $data;
    }

    protected function afterCreate(): void
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
