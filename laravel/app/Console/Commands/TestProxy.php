<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * php artisan  proxy:test
 */
class TestProxy extends Command
{
    protected $signature = 'proxy:test
                            {key? : API 密钥 (不填则使用第一个活跃的 Key)}
                            {model? : 模型名称}
                            {--prompt= : 测试提示语}
                            {--stream : 使用流式输出}';

    protected $description = '测试本站代理 API 连接';

    public function handle(): int
    {
        $apiKey = $this->argument('key');

        if (empty($apiKey)) {
            $firstKey = ApiKey::where('status', 'active')
                ->orderBy('id')
                ->first();

            if (! $firstKey) {
                $this->error('没有找到活跃的 API Key，请手动指定密钥');

                return self::FAILURE;
            }

            $apiKey = $firstKey->key;
            $this->info("使用默认 Key: {$firstKey->getMaskedKey()} (ID: {$firstKey->id})");
        }

        $model = $this->argument('model');

        if (empty($model)) {
            $model = $this->askForModel();
        }
        $prompt = $this->option('prompt') ?: '你好啊,介绍一下自己.';
        $useStream = $this->option('stream');
        $baseUrl = config('app.url').'/api/openai/v1';

        $this->info('=== 本站代理 API 测试 ===');
        $this->info("Base URL: {$baseUrl}");
        $this->info("模型: {$model}");
        $this->info("提示语: {$prompt}");
        $this->info('流式输出: '.($useStream ? '是' : '否'));
        $this->newLine();

        try {
            $startTime = microtime(true);

            if ($useStream) {
                return $this->streamResponseNative($baseUrl, $apiKey, $model, $prompt, $startTime);
            }

            return $this->standardResponseNative($baseUrl, $apiKey, $model, $prompt, $startTime);

        } catch (\Throwable $e) {
            $this->error('请求失败: '.$e->getMessage());
            $this->error('异常类型: '.get_class($e));

            if ($e->getPrevious()) {
                $this->error('前一个异常: '.$e->getPrevious()->getMessage());
            }

            $this->error('堆栈: '.$e->getTraceAsString());

            return self::FAILURE;
        }
    }

    protected function standardResponseNative(string $baseUrl, string $apiKey, string $model, string $prompt, float $startTime): int
    {
        $this->info('发送请求...');

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/chat/completions", [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 100,
            'temperature' => 0.1,
        ]);

        $latency = round((microtime(true) - $startTime) * 1000, 2);

        if (! $response->successful()) {
            $this->error("请求失败: {$response->status()}");
            $this->error("响应: {$response->body()}");

            return self::FAILURE;
        }

        $data = $response->json();

        $this->info('✓ 请求成功');
        $this->info("延迟: {$latency}ms");
        $this->newLine();

        $this->info('--- 响应内容 ---');
        $content = $data['choices'][0]['message']['content'] ?? '';
        if (empty($content) && isset($data['choices'][0]['message']['reasoning_content'])) {
            $content = $data['choices'][0]['message']['reasoning_content'];
        }
        $this->line($content);
        $this->info('--- 响应结束 ---');
        $this->newLine();

        if (isset($data['usage'])) {
            $this->info('Token 使用:');
            $this->info("  提示词: {$data['usage']['prompt_tokens']}");
            $this->info("  完成: {$data['usage']['completion_tokens']}");
            $this->info("  总计: {$data['usage']['total_tokens']}");
        }

        $this->newLine();
        $this->info("模型: {$data['model']}");
        $this->info("完成原因: {$data['choices'][0]['finish_reason']}");

        return self::SUCCESS;
    }

    protected function streamResponseNative(string $baseUrl, string $apiKey, string $model, string $prompt, float $startTime): int
    {
        $this->info('发送流式请求...');
        $this->newLine();
        $this->info('--- 响应流 ---');

        $url = "{$baseUrl}/chat/completions";
        $payload = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 100,
            'temperature' => 0.1,
            'stream' => true,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($startTime, &$firstToken, &$ttft, &$fullContent) {
                if ($firstToken) {
                    $ttft = round((microtime(true) - $startTime) * 1000, 2);
                    $firstToken = false;
                }

                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || ! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $json = trim(substr($line, 5));
                    if ($json === '[DONE]') {
                        return strlen($data);
                    }

                    $parsed = json_decode($json, true);
                    if (! $parsed) {
                        continue;
                    }

                    $choices = $parsed['choices'] ?? [];
                    $choice = $choices[0] ?? [];
                    $delta = $choice['delta'] ?? [];

                    $content = $delta['content'] ?? '';
                    if (empty($content) && isset($delta['reasoning_content'])) {
                        $content = $delta['reasoning_content'];
                    }

                    if ($content) {
                        echo $content;
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                        $fullContent .= $content;
                    }
                }

                return strlen($data);
            },
        ]);

        $firstToken = true;
        $ttft = 0;
        $fullContent = '';

        curl_exec($ch);
        curl_close($ch);

        $latency = round((microtime(true) - $startTime) * 1000, 2);

        $this->newLine();
        $this->info('--- 响应流结束 ---');
        $this->newLine();

        $this->info('✓ 请求成功');
        $this->info("首字延迟 (TTFT): {$ttft}ms");
        $this->info("总延迟: {$latency}ms");

        return self::SUCCESS;
    }

    protected function askForModel(): string
    {
        $models = [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
            'claude-3-5-sonnet-20241022',
            'claude-3-haiku-20240307',
            'deepseek-chat',
            'deepseek-coder',
        ];

        $choices = array_merge($models, ['自定义']);
        $selected = $this->choice('请选择模型', $choices, 0);

        if ($selected === '自定义') {
            return $this->ask('请输入模型名称');
        }

        return $selected;
    }
}
