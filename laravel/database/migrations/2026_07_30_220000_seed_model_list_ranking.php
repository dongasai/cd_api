<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 填充全球排行前40模型到 model_lists 表
     * 数据来源: database/data/model_ranking.php (由 cdapi:stats:model-ranking --save 生成)
     */
    public function up(): void
    {
        $dataPath = database_path('data/model_ranking.php');

        if (! file_exists($dataPath)) {
            // 如果数据文件不存在，使用内置默认数据
            $models = $this->getDefaultModels();
        } else {
            $models = require $dataPath;
        }

        $now = now();

        foreach ($models as $model) {
            $insertData = [
                'model_name' => $model['model_name'],
                'display_name' => $model['display_name'],
                'provider' => $model['provider'],
                'hugging_face_id' => $model['hugging_face_id'] ?? null,
                'common_name' => $model['common_name'],
                'context_length' => $model['context_length'],
                'pricing_prompt' => $model['pricing_prompt'] !== null ? (float) $model['pricing_prompt'] : null,
                'pricing_completion' => $model['pricing_completion'] !== null ? (float) $model['pricing_completion'] : null,
                'pricing_input_cache_read' => isset($model['pricing_input_cache_read']) && $model['pricing_input_cache_read'] !== null ? (float) $model['pricing_input_cache_read'] : null,
                'capabilities' => json_encode($model['capabilities']),
                'config' => isset($model['config']) ? json_encode($model['config']) : null,
                'is_enabled' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            DB::table('model_lists')->upsert(
                $insertData,
                ['model_name'],
                ['display_name', 'provider', 'hugging_face_id', 'common_name', 'context_length', 'pricing_prompt', 'pricing_completion', 'pricing_input_cache_read', 'capabilities', 'config', 'is_enabled', 'updated_at']
            );
        }
    }

    public function down(): void
    {
        $dataPath = database_path('data/model_ranking.php');

        if (! file_exists($dataPath)) {
            $models = $this->getDefaultModels();
        } else {
            $models = require $dataPath;
        }

        $modelNames = array_map(fn ($m) => $m['model_name'], $models);

        DB::table('model_lists')->whereIn('model_name', $modelNames)->delete();
    }

    /**
     * 默认数据（当数据文件不存在时使用）
     */
    private function getDefaultModels(): array
    {
        return [
            ['model_name' => 'claude-mythos-5', 'display_name' => 'Claude Mythos 5', 'provider' => 'anthropic', 'common_name' => 'Mythos 5', 'context_length' => 500000, 'pricing_prompt' => 10, 'pricing_completion' => 50, 'capabilities' => ['coding', 'agentic', 'knowledge', 'multimodal']],
            ['model_name' => 'claude-opus-5', 'display_name' => 'Claude Opus 5', 'provider' => 'anthropic', 'common_name' => 'Opus 5', 'context_length' => 500000, 'pricing_prompt' => 5, 'pricing_completion' => 25, 'capabilities' => ['coding', 'agentic', 'reasoning', 'knowledge', 'multimodal']],
            ['model_name' => 'claude-fable-5', 'display_name' => 'Claude Fable 5', 'provider' => 'anthropic', 'common_name' => 'Fable 5', 'context_length' => 500000, 'pricing_prompt' => 10, 'pricing_completion' => 50, 'capabilities' => ['coding', 'agentic', 'knowledge', 'multimodal']],
            ['model_name' => 'gpt-5.6-sol', 'display_name' => 'GPT-5.6 Sol', 'provider' => 'openai', 'common_name' => '5.6 Sol', 'context_length' => 256000, 'pricing_prompt' => 5, 'pricing_completion' => 30, 'capabilities' => ['coding', 'agentic', 'reasoning', 'knowledge', 'multimodal', 'math']],
            ['model_name' => 'kimi-k3', 'display_name' => 'Kimi K3', 'provider' => 'moonshot', 'common_name' => 'K3', 'context_length' => 256000, 'pricing_prompt' => 3, 'pricing_completion' => 15, 'capabilities' => ['coding', 'agentic', 'knowledge', 'multimodal']],
        ];
    }
};