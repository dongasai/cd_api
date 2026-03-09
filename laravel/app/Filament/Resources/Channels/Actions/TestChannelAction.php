<?php

namespace App\Filament\Resources\Channels\Actions;

use App\Models\Channel;
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

        try {
            $startTime = microtime(true);

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
            $response = $provider->send($providerRequest);

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            $content = $response->content ?? '无内容';

            Notification::make()
                ->title('测试成功')
                ->body("延迟: {$latencyMs}ms\n响应: ".substr($content, 0, 100))
                ->success()
                ->duration(5000)
                ->send();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            Log::error('Channel test failed', [
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
