<?php

namespace App\Providers;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Services\CodingStatus\SlidingWindowRepository;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SlidingWindowRepository::class);
    }

    public function boot(): void
    {
        
    }
}
