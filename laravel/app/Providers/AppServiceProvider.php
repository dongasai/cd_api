<?php

namespace App\Providers;

use App\Services\CodingStatus\SlidingWindowRepository;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SlidingWindowRepository::class);
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_AFTER,
            function () {
                $currentRoute = request()->route()?->getName();
                if ($currentRoute === 'filament.admin.resources.audit-logs.index') {
                    return Blade::render(<<<'HTML'
<div class="px-4 py-3 text-sm font-medium text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
    <div class="flex items-center gap-4">
        <div style="width: 70px; flex-shrink: 0;">ID</div>
        <div style="width: 120px; flex-shrink: 0;">时间</div>
        <div style="width: 120px; flex-shrink: 0;">渠道</div>
        <div style="width: 80px; flex-shrink: 0;">令牌</div>
        <div style="flex: 1; min-width: 0;">模型</div>
        <div style="width: 100px; flex-shrink: 0;">用时/首字</div>
        <div style="width: 70px; flex-shrink: 0;">输入</div>
        <div style="width: 70px; flex-shrink: 0;">输出</div>
    </div>
</div>
HTML);
                }

                return '';
            }
        );
    }
}
