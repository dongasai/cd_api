<?php

namespace App\Providers;

use App\Services\ChannelAffinity\ChannelAffinityService;
use App\Services\CodingStatus\ChannelCodingStatusService;
use App\Services\CodingStatus\ChannelErrorHandlingService;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\ProviderManager;
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
                $app->make(ProtocolConverter::class),
                $app->make(ProviderManager::class),
                $app->make(ChannelRouterService::class),
                $app->make(ChannelCodingStatusService::class),
                $app->make(ChannelErrorHandlingService::class),
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
