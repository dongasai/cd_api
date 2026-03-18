<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI;
use OpenAI\Client;

class TestOpenAI extends Command
{
    protected $signature = 'cdapi:openai:test
                            {--key= : API Key (不填则使用环境变量)}
                            {--base-url= : API Base URL (不填则使用默认或环境变量)}
                            {--model= : 模型名称}
                            {--prompt= : 测试提示语}
                            {--stream : 使用流式输出}';

    protected $description = '使用 openai-php SDK 测试 API 连接';

    public function handle(): int
    {
        $apiKey = $this->option('key') ?: env('OPENAI_API_KEY');
        $baseUrl = $this->option('base-url') ?: env('OPENAI_BASE_URL');
        $model = $this->option('model') ?: $this->askForModel();
        $prompt = $this->option('prompt') ?: 'Hi, please respond with "OK" to confirm you are working.';
        $useStream = $this->option('stream');

        if (empty($apiKey)) {
            $this->error('请提供 API Key (--key 参数) 或设置 OPENAI_API_KEY 环境变量');

            return self::FAILURE;
        }

        if (empty($model)) {
            $this->error('请提供模型名称 (--model 参数)');

            return self::FAILURE;
        }

        $this->info('=== OpenAI API 测试 ===');
        $this->info('Base URL: '.($baseUrl ?: 'https://api.openai.com/v1'));
        $this->info("模型: {$model}");
        $this->info("提示语: {$prompt}");
        $this->info('流式输出: '.($useStream ? '是' : '否'));
        $this->newLine();

        $config = [
            'api_key' => $apiKey,
        ];

        if ($baseUrl) {
            $config['base_uri'] = $baseUrl;
        }

        $client = OpenAI::factory()
            ->withApiKey($config['api_key'])
            ->when($baseUrl, fn ($factory, $url) => $factory->withBaseUri($url))
            ->make();

        try {
            $startTime = microtime(true);

            if ($useStream) {
                return $this->streamResponse($client, $model, $prompt, $startTime);
            }

            return $this->standardResponse($client, $model, $prompt, $startTime);

        } catch (\Throwable $e) {
            $this->error('请求失败: '.$e->getMessage());

            if (method_exists($e, 'getResponse')) {
                $response = $e->getResponse();
                if ($response) {
                    $this->error('响应状态: '.$response->getStatusCode());
                    $this->error('响应内容: '.$response->getBody()->getContents());
                }
            }

            return self::FAILURE;
        }
    }

    protected function askForModel(): ?string
    {
        $models = [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4',
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

    protected function standardResponse(Client $client, string $model, string $prompt, float $startTime): int
    {
        $this->info('发送请求...');

        $response = $client->chat()->create([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 100,
            'temperature' => 0.1,
        ]);

        $latency = round((microtime(true) - $startTime) * 1000, 2);

        $this->info('✓ 请求成功');
        $this->info("延迟: {$latency}ms");
        $this->newLine();

        $this->info('--- 响应内容 ---');
        $content = $response->choices[0]->message->content ?? '';
        $this->line($content);
        $this->info('--- 响应结束 ---');
        $this->newLine();

        if (isset($response->usage)) {
            $this->info('Token 使用:');
            $this->info("  提示词: {$response->usage->promptTokens}");
            $this->info("  完成: {$response->usage->completionTokens}");
            $this->info("  总计: {$response->usage->totalTokens}");
        }

        $this->newLine();
        $this->info("模型: {$response->model}");
        $this->info("完成原因: {$response->choices[0]->finishReason}");

        return self::SUCCESS;
    }

    protected function streamResponse(Client $client, string $model, string $prompt, float $startTime): int
    {
        $this->info('发送流式请求...');
        $this->newLine();
        $this->info('--- 响应流 ---');

        $stream = $client->chat()->createStreamed([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 100,
            'temperature' => 0.1,
        ]);

        $fullContent = '';
        $firstToken = true;
        $ttft = 0;

        foreach ($stream as $chunk) {
            if ($firstToken) {
                $ttft = round((microtime(true) - $startTime) * 1000, 2);
                $firstToken = false;
            }

            $content = $chunk->choices[0]->delta->content ?? '';
            if ($content) {
                $this->getOutput()->write($content);
                $fullContent .= $content;
            }
        }

        $latency = round((microtime(true) - $startTime) * 1000, 2);

        $this->newLine();
        $this->info('--- 响应流结束 ---');
        $this->newLine();

        $this->info('✓ 请求成功');
        $this->info("首字延迟 (TTFT): {$ttft}ms");
        $this->info("总延迟: {$latency}ms");

        return self::SUCCESS;
    }
}
