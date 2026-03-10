<?php

namespace App\Providers;

use App\Services\ChannelAffinity\ChannelAffinityService;
use App\Services\Router\ChannelRouterService;
use App\Services\Router\ProxyServer;
use Illuminate\Support\ServiceProvider;

class RouterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelRouterService::class, function ($app) {
            return new ChannelRouterService([
                'cache_ttl' => config('router.cache_ttl', 60),
                'max_retry' => config('router.max_retry', 3),
                'enable_failover' => config('router.enable_failover', true),
            ]);
        });

        $this->app->singleton(ProxyServer::class, function ($app) {
            return new ProxyServer(
                $app->make(\App\Services\Protocol\ProtocolConverter::class),
                $app->make(\App\Services\Provider\ProviderManager::class),
                $app->make(ChannelRouterService::class),
                $app->make(\App\Services\CodingStatus\ChannelCodingStatusService::class),
                $app->make(ChannelAffinityService::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/router.php' => config_path('router.php'),
        ], 'config');
    }
}
