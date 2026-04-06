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
        // 检查 channels 表是否存在
        if (! Schema::hasTable('channels')) {
            return;
        }

        // 构建查询，只选择存在的列
        $query = DB::table('channels');
        $columns = Schema::getColumnListing('channels');

        $hasModels = in_array('models', $columns);
        $hasDefaultModel = in_array('default_model', $columns);
        $hasModelMappings = in_array('model_mappings', $columns);

        // 如果没有任何相关列，直接返回
        if (! $hasModels && ! $hasDefaultModel && ! $hasModelMappings) {
            return;
        }

        // 构建条件
        $channels = DB::table('channels')
            ->when($hasModels, fn ($q) => $q->orWhereNotNull('models'))
            ->when($hasDefaultModel, fn ($q) => $q->orWhereNotNull('default_model'))
            ->when($hasModelMappings, fn ($q) => $q->orWhereNotNull('model_mappings'))
            ->get();

        foreach ($channels as $channel) {
            $models = json_decode($channel->models ?? '{}', true) ?: [];
            $modelMappings = $hasModelMappings ? (json_decode($channel->model_mappings ?? '{}', true) ?: []) : [];
            $defaultModel = $hasDefaultModel ? ($channel->default_model ?? null) : null;

            // 如果没有 models 但有 default_model，创建一个默认模型记录
            if (empty($models) && $defaultModel) {
                $models = [$defaultModel => $defaultModel];
            }

            // 如果没有 models 也没有 default_model，但有 model_mappings，使用 mappings 的 keys
            if (empty($models) && ! empty($modelMappings)) {
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

                if (! $exists) {
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
                if (! isset($models[$modelName])) {
                    $isDefault = ($modelName === $defaultModel);

                    $exists = DB::table('channel_models')
                        ->where('channel_id', $channel->id)
                        ->where('model_name', $modelName)
                        ->exists();

                    if (! $exists) {
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
