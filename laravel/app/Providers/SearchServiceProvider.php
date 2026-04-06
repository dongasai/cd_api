<?php

namespace App\Providers;

use App\Services\Search\SearchDriverManager;
use Illuminate\Support\ServiceProvider;

/**
 * 搜索服务提供者
 */
class SearchServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册驱动管理器
        $this->app->singleton(SearchDriverManager::class, function ($app) {
            $manager = new SearchDriverManager;

            // 设置默认驱动
            $defaultDriver = config('search.default', 'mock');
            $manager->setDefaultDriver($defaultDriver);

            return $manager;
        });
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__.'/../../config/search.php' => config_path('search.php'),
        ], 'search-config');
    }
}
