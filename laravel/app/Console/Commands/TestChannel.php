<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\Driver\AnthropicProvider;
use App\Services\Provider\Driver\OpenAICompatibleProvider;
use App\Services\Provider\Driver\OpenAIProvider;
use App\Services\Provider\Exceptions\ProviderException;
use Illuminate\Console\Command;

class TestChannel extends Command
{
    protected $signature = 'channel:test
                            {channel? : 渠道ID或渠道名称}
                            {--all : 测试所有启用的渠道}
                            {--model= : 指定测试使用的模型}
                            {--timeout=30 : 请求超时时间(秒)}';

    protected $description = '测试渠道连接是否正常';

    protected array $providerMap = [
        'openai' => OpenAIProvider::class,
        'anthropic' => AnthropicProvider::class,
    ];

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->testAllChannels();
        }

        $channelIdOrName = $this->argument('channel');

        if (!$channelIdOrName) {
            $channelIdOrName = $this->askForChannel();
            if (!$channelIdOrName) {
                $this->error('请指定要测试的渠道');
                return self::FAILURE;
            }
        }

        $channel = $this->findChannel($channelIdOrName);

        if (!$channel) {
            $this->error("渠道不存在: {$channelIdOrName}");
            return self::FAILURE;
        }

        return $this->testChannel($channel);
    }

    protected function askForChannel(): ?string
    {
        $channels = Channel::where('status', 'active')->get(['id', 'name', 'provider']);

        if ($channels->isEmpty()) {
            $this->warn('没有找到启用的渠道');
            return null;
        }

        $choices = $channels->map(fn ($c) => "[{$c->id}] {$c->name} ({$c->provider})")->toArray();
        $choices[] = '取消';

        $selected = $this->choice('请选择要测试的渠道', $choices, count($choices) - 1);

        if ($selected === '取消') {
            return null;
        }

        preg_match('/\[(\d+)\]/', $selected, $matches);
        return $matches[1] ?? null;
    }

    protected function findChannel(string $idOrName): ?Channel
    {
        if (is_numeric($idOrName)) {
            return Channel::find((int) $idOrName);
        }

        return Channel::where('name', $idOrName)->first()
            ?? Channel::where('slug', $idOrName)->first();
    }

    protected function testAllChannels(): int
    {
        $channels = Channel::where('status', 'active')->get();

        if ($channels->isEmpty()) {
            $this->warn('没有找到启用的渠道');
            return self::SUCCESS;
        }

        $this->info("开始测试 {$channels->count()} 个渠道...");
        $this->newLine();

        $success = 0;
        $failed = 0;

        foreach ($channels as $channel) {
            $result = $this->testChannel($channel, false);
            if ($result === self::SUCCESS) {
                $success++;
            } else {
                $failed++;
            }
        }

        $this->newLine();
        $this->info("测试完成: 成功 {$success} 个, 失败 {$failed} 个");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function testChannel(Channel $channel, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("测试渠道: {$channel->name} (ID: {$channel->id})");
            $this->info("Provider: {$channel->provider}");
            $this->info("Base URL: {$channel->base_url}");
        }

        if (empty($channel->api_key)) {
            $this->error("  ✗ 渠道未配置 API Key");
            return self::FAILURE;
        }

        $model = $this->option('model') ?? $channel->getDefaultModelName() ?? $this->getDefaultModelForProvider($channel->provider);

        if (!$model) {
            $this->error("  ✗ 未找到可用的模型");
            return self::FAILURE;
        }

        if ($verbose) {
            $this->info("测试模型: {$model}");
        }

        try {
            $provider = $this->createProvider($channel);

            $startTime = microtime(true);

            $request = new ProviderRequest(
                model: $model,
                messages: [
                    ['role' => 'user', 'content' => 'Hi, please respond with "OK" to confirm you are working.'],
                ],
                maxTokens: 10,
                temperature: 0.1,
            );

            $response = $provider->send($request);

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            if ($verbose) {
                $this->info("  ✓ 连接成功");
                $this->info("  延迟: {$latency}ms");

                if ($response->content) {
                    $preview = mb_substr($response->content, 0, 100);
                    $this->info("  响应: {$preview}");
                }

                if ($response->usage) {
                    $this->info("  Token使用: 输入 {$response->usage->promptTokens}, 输出 {$response->usage->completionTokens}");
                }
            } else {
                $this->info("  ✓ [{$channel->id}] {$channel->name} - {$latency}ms");
            }

            $channel->update([
                'health_status' => 'healthy',
                'last_check_at' => now(),
                'last_success_at' => now(),
                'success_count' => ($channel->success_count ?? 0) + 1,
                'avg_latency_ms' => $channel->avg_latency_ms
                    ? round(($channel->avg_latency_ms + $latency) / 2, 2)
                    : $latency,
            ]);

            return self::SUCCESS;

        } catch (ProviderException $e) {
            return $this->handleError($channel, $e, $verbose);
        } catch (\Throwable $e) {
            return $this->handleError($channel, new ProviderException($e->getMessage(), previous: $e), $verbose);
        }
    }

    protected function handleError(Channel $channel, ProviderException $e, bool $verbose): int
    {
        $errorMessage = $e->getMessage();

        if ($verbose) {
            $this->error("  ✗ 连接失败");
            $this->error("  错误: {$errorMessage}");

            if ($e->getContext()) {
                $this->error("  详情: " . json_encode($e->getContext(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
        } else {
            $this->error("  ✗ [{$channel->id}] {$channel->name} - {$errorMessage}");
        }

        $channel->update([
            'health_status' => 'unhealthy',
            'last_check_at' => now(),
            'last_failure_at' => now(),
            'failure_count' => ($channel->failure_count ?? 0) + 1,
        ]);

        return self::FAILURE;
    }

    protected function createProvider(Channel $channel): OpenAIProvider|AnthropicProvider|OpenAICompatibleProvider
    {
        $config = [
            'base_url' => $channel->base_url,
            'api_key' => $channel->api_key,
            'timeout' => (int) $this->option('timeout'),
            'connect_timeout' => 10,
        ];

        $provider = $channel->provider;

        if (isset($this->providerMap[$provider])) {
            $class = $this->providerMap[$provider];
            return new $class($config);
        }

        return new OpenAICompatibleProvider(array_merge($config, [
            'name' => $provider,
        ]));
    }

    protected function getDefaultModelForProvider(string $provider): ?string
    {
        return match ($provider) {
            'openai' => 'gpt-3.5-turbo',
            'anthropic' => 'claude-3-haiku-20240307',
            'azure' => 'gpt-35-turbo',
            default => null,
        };
    }
}
