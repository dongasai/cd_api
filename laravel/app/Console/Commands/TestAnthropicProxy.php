<?php

namespace App\Console\Commands;

use Anthropic\Client;
use Anthropic\Messages\Message;
use App\Models\ApiKey;
use Illuminate\Console\Command;

/**
 * php artisan  proxy:test-anthropic "" Step-3.5-Flash
 */
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
        $maxTokens = (int) ($this->option('max-tokens') ?: 1024);
        $baseUrl = config('app.url').'/api/anthropic';

        $this->info('=== 本站 Anthropic 代理 API 测试 ===');
        $this->info("Base URL: {$baseUrl}");
        $this->info("模型: {$model}");
        $this->info("提示语: {$prompt}");
        $this->info("最大Tokens: {$maxTokens}");
        $this->info('流式输出: '.($useStream ? '是' : '否'));
        $this->newLine();

        $client = new Client(
            apiKey: $apiKey,
            baseUrl: $baseUrl
        );

        try {
            $startTime = microtime(true);

            if ($useStream) {
                return $this->streamResponse($client, $model, $prompt, $maxTokens, $startTime);
            }

            return $this->standardResponse($client, $model, $prompt, $maxTokens, $startTime);

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

    protected function standardResponse(Client $client, string $model, string $prompt, int $maxTokens, float $startTime): int
    {
        $this->info('发送请求...');

        $message = $client->messages->create(
            maxTokens: $maxTokens,
            model: $model,
            messages: [
                ['role' => 'user', 'content' => $prompt],
            ]
        );

        $latency = round((microtime(true) - $startTime) * 1000, 2);

        $this->info('✓ 请求成功');
        $this->info("延迟: {$latency}ms");
        $this->newLine();

        $this->info('--- 响应内容 ---');
        $content = $this->extractContent($message);
        $this->line($content);
        $this->info('--- 响应结束 ---');
        $this->newLine();

        if (isset($message->usage)) {
            $this->info('Token 使用:');
            $this->info("  输入: {$message->usage->inputTokens}");
            $this->info("  输出: {$message->usage->outputTokens}");
        }

        $this->newLine();
        $this->info("模型: {$message->model}");
        $this->info("角色: {$message->role}");
        $this->info("停止原因: {$message->stopReason}");

        return self::SUCCESS;
    }

    protected function streamResponse(Client $client, string $model, string $prompt, int $maxTokens, float $startTime): int
    {
        $this->info('发送流式请求...');
        $this->newLine();
        $this->info('--- 响应流 ---');

        $stream = $client->messages->createStream(
            maxTokens: $maxTokens,
            model: $model,
            messages: [
                ['role' => 'user', 'content' => $prompt],
            ]
        );

        $firstToken = true;
        $ttft = 0;
        $fullContent = '';

        foreach ($stream as $event) {
            if ($firstToken) {
                $ttft = round((microtime(true) - $startTime) * 1000, 2);
                $firstToken = false;
            }

            $text = $this->extractStreamText($event);

            if ($text) {
                echo $text;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                $fullContent .= $text;
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

    protected function extractContent(Message $message): string
    {
        $content = '';

        foreach ($message->content as $block) {
            if (isset($block->text)) {
                $content .= $block->text;
            }
        }

        return $content;
    }

    protected function extractStreamText(mixed $event): string
    {
        if (! is_object($event)) {
            return '';
        }

        if (property_exists($event, 'delta') && is_object($event->delta)) {
            if (property_exists($event->delta, 'text')) {
                return $event->delta->text;
            }
        }

        return '';
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
