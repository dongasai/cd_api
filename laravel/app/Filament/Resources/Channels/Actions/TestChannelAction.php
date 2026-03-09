<?php

namespace App\Filament\Resources\Channels\Actions;

use App\Models\Channel;
use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\ProviderManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;

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
            ->icon('heroicon-o-bolt')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('测试渠道')
            ->modalDescription(fn (Channel $record): string => "确定要测试渠道 \"{$record->name}\" 吗？将使用默认模型发送测试请求。")
            ->modalSubmitActionLabel('开始测试')
            ->action(function (Channel $record): void {
                $result = $this->testChannel($record);

                if ($result['success']) {
                    Notification::make()
                        ->title('测试成功')
                        ->body($result['message'])
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('测试失败')
                        ->body($result['message'])
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * 测试渠道连通性
     *
     * @param  Channel  $channel  渠道实例
     * @return array 测试结果
     */
    protected function testChannel(Channel $channel): array
    {
        // 获取默认模型
        $defaultModel = $channel->getDefaultModelName();

        if (empty($defaultModel)) {
            // 如果没有默认模型，尝试获取第一个启用的模型
            $models = $channel->getModelsArray();
            if (empty($models)) {
                return [
                    'success' => false,
                    'message' => '渠道没有配置任何模型',
                ];
            }
            $defaultModel = array_key_first($models);
        }

        // 检查渠道配置
        if (empty($channel->base_url)) {
            return [
                'success' => false,
                'message' => '渠道未配置 Base URL',
            ];
        }

        if (empty($channel->api_key)) {
            return [
                'success' => false,
                'message' => '渠道未配置 API Key',
            ];
        }

        try {
            $startTime = microtime(true);

            // 构建测试请求
            $testMessages = [
                ['role' => 'system', 'content' => '你是一个有用的助手'],
                ['role' => 'user', 'content' => '你好，这是一个测试消息，请回复"测试成功"'],
            ];

            $requestData = [
                'model' => $defaultModel,
                'messages' => $testMessages,
                'max_tokens' => 50,
                'temperature' => 0.7,
            ];

            $providerRequest = ProviderRequest::fromArray($requestData);

            // 使用 ProviderManager 获取供应商实例
            $providerManager = app(ProviderManager::class);
            $provider = $providerManager->getForChannel($channel);

            // 发送请求
            $response = $provider->send($providerRequest);

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            // 更新渠道健康状态
            $channel->update([
                'health_status' => 'healthy',
                'last_check_at' => now(),
                'last_success_at' => now(),
            ]);

            $content = $response->content ?? '无内容';

            return [
                'success' => true,
                'message' => "渠道测试成功！\n模型: {$defaultModel}\n延迟: {$latencyMs}ms\n响应: ".substr($content, 0, 100),
            ];
        } catch (\Exception $e) {
            // 更新渠道健康状态
            $channel->update([
                'health_status' => 'unhealthy',
                'last_check_at' => now(),
                'last_failure_at' => now(),
                'failure_count' => $channel->failure_count + 1,
            ]);

            return [
                'success' => false,
                'message' => "测试失败: ".$e->getMessage(),
            ];
        }
    }
}
