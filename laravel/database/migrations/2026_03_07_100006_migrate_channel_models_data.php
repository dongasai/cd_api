<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 获取所有有 models 或 default_model 或 model_mappings 数据的渠道
        $channels = DB::table('channels')
            ->whereNotNull('models')
            ->orWhereNotNull('default_model')
            ->orWhereNotNull('model_mappings')
            ->get();

        foreach ($channels as $channel) {
            $models = json_decode($channel->models ?? '{}', true) ?: [];
            $modelMappings = json_decode($channel->model_mappings ?? '{}', true) ?: [];
            $defaultModel = $channel->default_model;

            // 如果没有 models 但有 default_model，创建一个默认模型记录
            if (empty($models) && $defaultModel) {
                $models = [$defaultModel => $defaultModel];
            }

            // 如果没有 models 也没有 default_model，但有 model_mappings，使用 mappings 的 keys
            if (empty($models) && !empty($modelMappings)) {
                $models = array_combine(
                    array_keys($modelMappings),
                    array_keys($modelMappings)
                );
            }

            // 为每个模型创建 channel_models 记录
            foreach ($models as $modelName => $displayName) {
                $mappedModel = $modelMappings[$modelName] ?? null;
                $isDefault = ($modelName === $defaultModel);

                // 检查是否已存在
                $exists = DB::table('channel_models')
                    ->where('channel_id', $channel->id)
                    ->where('model_name', $modelName)
                    ->exists();

                if (!$exists) {
                    DB::table('channel_models')->insert([
                        'channel_id' => $channel->id,
                        'model_name' => $modelName,
                        'display_name' => is_string($displayName) ? $displayName : $modelName,
                        'mapped_model' => $mappedModel,
                        'is_default' => $isDefault,
                        'is_enabled' => true,
                        'multiplier' => 1.0000,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 处理只有 model_mappings 中没有在 models 中的映射
            foreach ($modelMappings as $modelName => $mappedModel) {
                if (!isset($models[$modelName])) {
                    $isDefault = ($modelName === $defaultModel);

                    $exists = DB::table('channel_models')
                        ->where('channel_id', $channel->id)
                        ->where('model_name', $modelName)
                        ->exists();

                    if (!$exists) {
                        DB::table('channel_models')->insert([
                            'channel_id' => $channel->id,
                            'model_name' => $modelName,
                            'display_name' => $modelName,
                            'mapped_model' => $mappedModel,
                            'is_default' => $isDefault,
                            'is_enabled' => true,
                            'multiplier' => 1.0000,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 数据迁移不支持回滚，因为原始数据可能已被修改
        // 如果需要回滚，需要手动处理
    }
};
