<?php

use App\Providers\AppServiceProvider;
use App\Providers\ProtocolServiceProvider;
use App\Providers\ProviderServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Providers\RouterServiceProvider;
use App\Providers\SearchServiceProvider;

return [
    AppServiceProvider::class,
    ProtocolServiceProvider::class,
    ProviderServiceProvider::class,
    RouterServiceProvider::class,
    SearchServiceProvider::class,
    RateLimitServiceProvider::class,
];
