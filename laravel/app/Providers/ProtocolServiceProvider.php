<?php

namespace App\Providers;

use App\Services\Protocol\Driver\AnthropicMessagesDriver;
use App\Services\Protocol\Driver\OpenAiChatCompletionsDriver;
use App\Services\Protocol\DriverManager;
use App\Services\Protocol\ProtocolConverter;
use Illuminate\Support\ServiceProvider;

/**
 * 协议服务提供者
 */
class ProtocolServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册驱动管理器
        $this->app->singleton(DriverManager::class, function ($app) {
            $manager = new DriverManager;

            // 注册默认驱动
            $manager->register('openai_chat_completions', function () {
                return new OpenAiChatCompletionsDriver;
            });

            $manager->register('anthropic_messages', function () {
                return new AnthropicMessagesDriver;
            });

            return $manager;
        });

        // 注册协议转换器
        $this->app->singleton(ProtocolConverter::class, function ($app) {
            return new ProtocolConverter($app->make(DriverManager::class));
        });
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        //
    }
}
