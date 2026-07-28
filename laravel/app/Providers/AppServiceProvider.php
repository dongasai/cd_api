<?php

namespace App\Providers;

use App\Services\CodingStatus\SlidingWindowRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        $this->app->singleton(SlidingWindowRepository::class);
    }

    /**
     * 引导服务
     */
    public function boot(): void
    {
        // 设置语言文件路径为 lang 目录
        $this->app->useLangPath(base_path('lang'));
    }
}
