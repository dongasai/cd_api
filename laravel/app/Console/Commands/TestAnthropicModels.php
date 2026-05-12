<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Services\ModelService;
use Illuminate\Console\Command;

class TestAnthropicModels extends Command
{
    protected $signature = 'test:anthropic-models {--api-key= : API Key ID to test}';

    protected $description = '测试 Anthropic models endpoint 响应格式';

    public function handle(): int
    {
        $apiKeyId = $this->option('api-key') ?? 1;

        $apiKey = ApiKey::find($apiKeyId);

        if (! $apiKey) {
            $this->error("API Key ID {$apiKeyId} 不存在");

            return self::FAILURE;
        }

        $this->info("使用 API Key: {$apiKey->name} (ID: {$apiKeyId})");

        // 获取模型列表（OpenAI 格式）
        $openaiData = ModelService::getAvailableModels($apiKey);

        $this->info("\nOpenAI 格式模型数量: ".count($openaiData));

        if (empty($openaiData)) {
            $this->warn('没有找到可用模型');

            return self::SUCCESS;
        }

        // 显示前3个 OpenAI 格式模型
        $this->info("\nOpenAI 格式示例（前3个）:");
        foreach (array_slice($openaiData, 0, 3) as $model) {
            $this->line(json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // 转换为 Anthropic 格式
        $anthropicData = array_map(function ($model) {
            return [
                'id' => $model['id'],
                'type' => 'model',
                'display_name' => $model['display_name'] ?? $model['id'],
                'created_at' => isset($model['created'])
                    ? date('c', $model['created'])
                    : date('c'),
            ];
        }, $openaiData);

        $response = [
            'data' => $anthropicData,
            'has_more' => false,
        ];

        if (! empty($anthropicData)) {
            $response['first_id'] = $anthropicData[0]['id'];
            $response['last_id'] = $anthropicData[count($anthropicData) - 1]['id'];
        }

        $this->info("\nAnthropic 格式完整响应:");
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("\n✅ 格式转换成功");

        return self::SUCCESS;
    }
}
