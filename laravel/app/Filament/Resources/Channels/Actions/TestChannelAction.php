<?php

namespace App\Filament\Resources\Channels\Actions;

use App\Models\Channel;
use App\Models\ChannelRequestLog;
use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\ProviderManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TestChannelAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'test';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('测试')
            ->icon(Heroicon::Bolt)
            ->color('warning')
            ->modalHeading(fn (Channel $record): string => "测试渠道: {$record->name}")
            ->modalDescription('点击测试按钮测试对应模型')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('关闭')
            ->modalWidth('2xl')
            ->schema(fn (Channel $record): array => $this->buildSchema($record))
            ->action(function () {});
    }

    protected function buildSchema(Channel $record): array
    {
        $models = $record->enabledModels()->get();

        if ($models->isEmpty()) {
            return [
                Text::make('该渠道没有配置任何启用的模型')
                    ->color('gray'),
            ];
        }

        $rows = [];
        foreach ($models as $model) {
            $rows[] = $this->buildModelRow($model, $record);
        }

        return [
            Section::make()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Text::make('模型名称')
                                ->weight(FontWeight::Bold)
                                ->color('gray'),
                            Text::make('显示名称')
                                ->weight(FontWeight::Bold)
                                ->color('gray'),
                            Text::make('操作')
                                ->weight(FontWeight::Bold)
                                ->color('gray'),
                        ])
                        ->columns(3),
                    ...$rows,
                ])
                ->compact(),
        ];
    }

    protected function buildModelRow($model, Channel $record): Grid
    {
        $modelName = $model->model_name;
        $displayName = $model->getDisplayName();
        $isDefault = $model->is_default ? ' (默认)' : '';

        return Grid::make(3)
            ->schema([
                Text::make($modelName)
                    ->weight(FontWeight::Medium),
                Text::make($displayName.$isDefault)
                    ->color($model->is_default ? 'success' : 'gray'),
                Actions::make([
                    Action::make("test_{$model->id}")
                        ->label('测试')
                        ->icon(Heroicon::Bolt)
                        ->color('warning')
                        ->size('sm')
                        ->action(function () use ($modelName, $record) {
                            $this->runTest($modelName, $record);
                        }),
                ]),
            ])
            ->columns(3);
    }

    protected function runTest(string $modelName, Channel $record): void
    {
        if (empty($record->base_url)) {
            Notification::make()
                ->title('测试失败')
                ->body('渠道未配置 Base URL')
                ->danger()
                ->duration(5000)
                ->send();

            return;
        }

        if (empty($record->api_key)) {
            Notification::make()
                ->title('测试失败')
                ->body('渠道未配置 API Key')
                ->danger()
                ->duration(5000)
                ->send();

            return;
        }

        $configHint = $this->checkConfig($record);
        if ($configHint !== null) {
            Notification::make()
                ->title('配置问题')
                ->body($configHint)
                ->warning()
                ->duration(8000)
                ->send();

            return;
        }

        $startTime = microtime(true);
        $requestId = 'test_'.Str::uuid()->toString();

        $testMessages = [
            ['role' => 'user', 'content' => 'Hi, please respond with "OK" to confirm you are working.'],
        ];

        $requestData = [
            'model' => $modelName,
            'messages' => $testMessages,
            'max_tokens' => 50,
            'temperature' => 0.7,
        ];

        $providerRequest = ProviderRequest::fromArray($requestData);
        $providerManager = app(ProviderManager::class);
        $provider = $providerManager->getForChannel($record);

        $baseUrl = rtrim($record->base_url, '/');
        $path = $this->buildEndpointPath($record->provider);
        $fullUrl = $baseUrl.$path;

        // 创建渠道请求日志
        $channelRequestLog = ChannelRequestLog::create([
            'request_id' => $requestId,
            'channel_id' => $record->id,
            'channel_name' => $record->name,
            'provider' => $record->provider,
            'method' => 'POST',
            'path' => $path,
            'base_url' => $baseUrl,
            'full_url' => $fullUrl,
            'request_headers' => $this->buildRequestHeaders($record),
            'request_body' => $requestData,
            'request_size' => strlen(json_encode($requestData)),
            'sent_at' => now(),
        ]);

        try {
            $response = $provider->send($providerRequest);

            // 从 Provider 获取实际请求信息
            $requestInfo = $provider->getLastRequestInfo();
            if ($requestInfo) {
                $channelRequestLog->update([
                    'path' => $requestInfo->path,
                    'full_url' => $requestInfo->url,
                    'request_headers' => $requestInfo->headers,
                    'request_body' => $requestInfo->body,
                    'request_size' => strlen(json_encode($requestInfo->body)),
                ]);
            }

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            $content = $response->content ?? '无内容';

            // 更新渠道请求日志，记录响应信息
            $channelRequestLog->update([
                'response_status' => 200,
                'response_headers' => ['content-type' => 'application/json'],
                'response_body' => $response->rawResponse ?? ['content' => $content],
                'response_size' => strlen(json_encode($response->rawResponse ?? ['content' => $content])),
                'latency_ms' => $latencyMs,
                'is_success' => true,
                'usage' => $response->usage ? [
                    'prompt_tokens' => $response->usage->promptTokens ?? 0,
                    'completion_tokens' => $response->usage->completionTokens ?? 0,
                    'total_tokens' => $response->usage->totalTokens ?? 0,
                ] : null,
            ]);

            Notification::make()
                ->title('测试成功')
                ->body("延迟: {$latencyMs}ms\n响应: ".substr($content, 0, 100))
                ->success()
                ->duration(5000)
                ->send();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // 更新渠道请求日志，记录错误信息
            $channelRequestLog->update([
                'response_status' => $this->getStatusCode($e),
                'is_success' => false,
                'error_type' => get_class($e),
                'error_message' => $errorMessage,
                'latency_ms' => $latencyMs,
            ]);

            Log::error('Channel test failed', [
                'request_id' => $requestId,
                'channel_id' => $record->id,
                'channel_name' => $record->name,
                'provider' => $record->provider,
                'base_url' => $record->base_url,
                'model' => $modelName,
                'error' => $errorMessage,
            ]);

            Notification::make()
                ->title('测试失败')
                ->body($errorMessage)
                ->danger()
                ->duration(8000)
                ->send();
        }
    }

    /**
     * 构建端点路径
     */
    protected function buildEndpointPath(string $provider): string
    {
        if (in_array($provider, ['anthropic', 'claude'])) {
            return '/messages';
        }

        return '/chat/completions';
    }

    /**
     * 构建请求头（用于日志记录，已过滤敏感信息）
     */
    protected function buildRequestHeaders(Channel $channel): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (in_array($channel->provider, ['anthropic', 'claude'])) {
            $headers['x-api-key'] = '***';
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['Authorization'] = 'Bearer ***';
        }

        return $headers;
    }

    /**
     * 获取异常的 HTTP 状态码
     */
    protected function getStatusCode(\Exception $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        if (method_exists($e, 'getCode') && $e->getCode() > 0) {
            return $e->getCode();
        }

        return 500;
    }

    protected function checkConfig(Channel $record): ?string
    {
        $baseUrl = rtrim($record->base_url, '/');

        if ($record->provider === 'anthropic') {
            if (! str_ends_with($baseUrl, '/v1') && ! str_contains($baseUrl, 'anthropic.com')) {
                return "base_url可能配置错误: {$baseUrl}\n\nAnthropic格式的API通常需要base_url以/v1结尾。\n例如: https://api.siliconflow.cn/v1";
            }
        }

        if ($record->provider === 'openai') {
            if (! str_ends_with($baseUrl, '/v1') && ! str_contains($baseUrl, 'openai.com')) {
                return "base_url可能配置错误: {$baseUrl}\n\nOpenAI格式的API通常需要base_url以/v1结尾。";
            }
        }

        return null;
    }
}
