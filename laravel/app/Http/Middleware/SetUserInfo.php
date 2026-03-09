<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetUserInfo
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            $locale = $user->locale;
            $currency = $user->currency ?? 'USD';

            session([
                'locale' => $locale,
                'currency' => $currency,
            ]);
        } else {
            $locale = session('locale', config('locale.default', config('app.locale')));
            $currency = session('currency', 'USD');
        }

        $availableLocales = array_keys(config('locale.available', []));

        if (! in_array($locale, $availableLocales)) {
            $locale = config('locale.default', 'zh_CN');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
