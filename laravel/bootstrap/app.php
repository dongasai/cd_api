<?php

use App\Http\Middleware\SetUserInfo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // 安装/升级路由使用 install 中间件组（空组，无加密/会话等中间件）
            Route::middleware('install')->group(base_path('routes/install.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 定义 install 中间件组（空组，不包含任何中间件）
        $middleware->group('install', []);

        $middleware->web(append: [
            SetUserInfo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $renderErrorView = function (Throwable $e, Request $request, int $statusCode) {
            if ($request->wantsJson()) {
                return null;
            }

            try {
                if (config('app.debug') && view()->exists('errors.debug')) {
                    return response()->view('errors.debug', ['exception' => $e], $statusCode);
                }

                $viewPath = "errors.{$statusCode}";
                if (view()->exists($viewPath)) {
                    return response()->view($viewPath, ['exception' => $e], $statusCode);
                }

                if (view()->exists('errors.500')) {
                    return response()->view('errors.500', ['exception' => $e], 500);
                }
            } catch (Throwable $viewError) {
                return response()->make(
                    '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.$statusCode.' Error</title>'.
                    '<style>body{font-family:system-ui,sans-serif;background:#f3f4f6;color:#1f2937;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0}'.
                    '.container{text-align:center;padding:2rem}h1{font-size:4rem;font-weight:700;color:#ef4444;margin:0}p{font-size:1.25rem;color:#6b7280;margin-top:1rem}</style>'.
                    '</head><body><div class="container"><h1>'.$statusCode.'</h1><p>'.e($e->getMessage()).'</p></div></body></html>',
                    $statusCode,
                    ['Content-Type' => 'text/html; charset=UTF-8']
                );
            }

            return null;
        };

        $exceptions->render(function (Throwable $e, Request $request) use ($renderErrorView) {
            // 处理验证异常，返回 JSON 格式
            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return null;
            }

            $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;

            return $renderErrorView($e, $request, $statusCode);
        });
    })->create();
