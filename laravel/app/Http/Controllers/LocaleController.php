<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    /**
     * 切换应用语言
     */
    public function __invoke(string $locale): RedirectResponse
    {
        $availableLocales = array_keys(config('locale.available', []));

        if (! in_array($locale, $availableLocales)) {
            $locale = config('locale.default', 'zh_CN');
        }

        // 保存到 session
        session(['locale' => $locale]);

        // 如果已登录，保存到用户
        if (Auth::check()) {
            Auth::user()->update(['locale' => $locale]);
        }

        return redirect()->back();
    }
}
