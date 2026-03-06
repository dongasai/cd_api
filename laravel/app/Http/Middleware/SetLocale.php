<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 优先从用户获取，其次是 session，最后是默认
        $locale = Auth::check()
            ? Auth::user()->locale
            : session('locale', config('locale.default', config('app.locale')));

        $availableLocales = array_keys(config('locale.available', []));

        if (! in_array($locale, $availableLocales)) {
            $locale = config('locale.default', 'zh_CN');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
