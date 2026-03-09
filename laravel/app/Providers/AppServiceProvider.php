<?php

namespace App\Providers;

use App\Services\CodingStatus\SlidingWindowRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SlidingWindowRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
