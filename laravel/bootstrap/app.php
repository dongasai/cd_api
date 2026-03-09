<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') || $request->wantsJson() || $request->is('livewire/*')) {
                return null;
            }

            if ($request->header('X-Livewire')) {
                return null;
            }

            if (config('app.debug') && view()->exists('errors.debug')) {
                return response()->view('errors.debug', ['exception' => $e], $e->getStatusCode());
            }

            $statusCode = $e->getStatusCode();
            $viewPath = "errors.{$statusCode}";

            if (view()->exists($viewPath)) {
                return response()->view($viewPath, ['exception' => $e], $statusCode);
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpException) {
                return null;
            }

            if ($request->is('api/*') || $request->wantsJson() || $request->is('livewire/*')) {
                return null;
            }

            if ($request->header('X-Livewire')) {
                return null;
            }

            if (config('app.debug') && view()->exists('errors.debug')) {
                return response()->view('errors.debug', ['exception' => $e], 500);
            }

            if (view()->exists('errors.500')) {
                return response()->view('errors.500', ['exception' => $e], 500);
            }

            return null;
        });
    })->create();
