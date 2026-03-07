<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAnthropicProxy extends Command
{
    protected $signature = 'proxy:test-anthropic
                            {key? : API 密钥 (不填则使用第一个活跃的 Key)}
                            {model? : 模型名称}
                            {--prompt= : 测试提示语}
                            {--stream : 使用流式输出}
                            {--max-tokens= : 最大生成token数}';

    protected $description = '测试本站 Anthropic 代理 API 连接';

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
        $maxTokens = $this->option('max-tokens') ?: 1024;
        $baseUrl = config('app.url').'/api/anthropic';

        $this->info('=== 本站 Anthropic 代理 API 测试 ===');
        $this->info("Base URL: {$baseUrl}");
        $this->info("模型: {$model}");
        $this->info("提示语: {$prompt}");
        $this->info("最大Tokens: {$maxTokens}");
        $this->info('流式输出: '.($useStream ? '是' : '否'));
        $this->newLine();

        try {
            $startTime = microtime(true);

            if ($useStream) {
                return $this->streamResponse($baseUrl, $apiKey, $model, $prompt, $maxTokens, $startTime);
            }

            return $this->standardResponse($baseUrl, $apiKey, $model, $prompt, $maxTokens, $startTime);

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

    protected function standardResponse(string $baseUrl, string $apiKey, string $model, string $prompt, int $maxTokens, float $startTime): int
    {
        $this->info('发送请求...');

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])->post(config('app.url').'/api/anthropic/messages', [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $maxTokens,
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
        $content = $data['content'][0]['text'] ?? '';
        $this->line($content);
        $this->info('--- 响应结束 ---');
        $this->newLine();

        if (isset($data['usage'])) {
            $this->info('Token 使用:');
            $this->info("  输入: {$data['usage']['input_tokens']}");
            $this->info("  输出: {$data['usage']['output_tokens']}");
        }

        $this->newLine();
        $this->info("模型: {$data['model']}");
        $this->info("角色: {$data['role']}");
        $this->info("停止原因: {$data['stop_reason']}");

        return self::SUCCESS;
    }

    protected function streamResponse(string $baseUrl, string $apiKey, string $model, string $prompt, int $maxTokens, float $startTime): int
    {
        $this->info('发送流式请求...');
        $this->newLine();
        $this->info('--- 响应流 ---');

        $url = config('app.url').'/api/anthropic/messages';
        $payload = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $maxTokens,
            'stream' => true,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'x-api-key: '.$apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2025-12-05',
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

                    $type = $parsed['type'] ?? '';

                    if ($type === 'content_block_delta') {
                        $delta = $parsed['delta'] ?? [];
                        $text = $delta['text'] ?? '';

                        if ($text) {
                            echo $text;
                            if (ob_get_level() > 0) {
                                ob_flush();
                            }
                            flush();
                            $fullContent .= $text;
                        }
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
            'claude-3-5-sonnet-20241022',
            'claude-3-5-sonnet-latest',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307',
            'claude-3-haiku-latest',
        ];

        $choices = array_merge($models, ['自定义']);
        $selected = $this->choice('请选择模型', $choices, 0);

        if ($selected === '自定义') {
            return $this->ask('请输入模型名称');
        }

        return $selected;
    }
}
