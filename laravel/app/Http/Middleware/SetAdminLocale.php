<?php

namespace App\Http\Middleware;

use Closure;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;

/**
 * 设置管理员界面语言的中间件
 *
 * 根据用户的 language 字段动态设置后台语言
 */
class SetAdminLocale
{
    /**
     * 处理请求
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 检查是否已登录管理员
        if ($user = Admin::user()) {
            // 获取用户的语言设置，如果没有则使用默认语言
            $locale = $user->language ?? 'zh_CN';

            // 设置应用语言
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
