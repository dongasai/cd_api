<?php

namespace App\Providers;

use App\Services\RateLimit\RateLimitManager;
use Illuminate\Support\ServiceProvider;

/**
 * 限流服务提供者
 */
class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 每次请求需要动态注册不同的限流器，不能使用 singleton
        $this->app->bind(RateLimitManager::class, function () {
            return new RateLimitManager;
        });
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__.'/../../config/rate_limit.php' => config_path('rate_limit.php'),
        ], 'rate-limit-config');
    }
}
