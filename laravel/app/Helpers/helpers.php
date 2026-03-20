<?php

use App\Services\SettingService;
use Illuminate\Support\Str;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingService::class)->get($key, $default);
    }
}

if (! function_exists('admin_trans_action')) {
    /**
     * 获取 Admin Action 翻译
     */
    function admin_trans_action(string $key, array $replace = []): string
    {
        return trans("admin-actions.{$key}", $replace);
    }
}

if (! function_exists('admin_get_controller_name')) {
    /**
     * 获取当前控制器名称（用于语言包定位）
     */
    function admin_get_controller_name(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($trace as $item) {
            if (! isset($item['class'])) {
                continue;
            }

            $class = $item['class'];

            // 匹配 App\Admin\Controllers\xxxController
            if (preg_match('/App\\\\Admin\\\\Controllers\\\\(\w+)Controller/', $class, $matches)) {
                return Str::kebab($matches[1]);
            }
        }

        return 'global';
    }
}

if (! function_exists('admin_trans')) {
    /**
     * 获取控制器语言包翻译
     *
     * @param  string  $key  翻译键名，支持 "field" 或 "options.status" 格式
     * @param  array  $replace  替换参数
     * @param  string|null  $controller  控制器名称，为空则自动检测
     */
    function admin_trans(string $key, array $replace = [], ?string $controller = null): string
    {
        $controller = $controller ?? admin_get_controller_name();

        return trans("admin-{$controller}.{$key}", $replace);
    }
}

if (! function_exists('admin_trans_field')) {
    /**
     * 获取字段翻译
     */
    function admin_trans_field(string $field, array $replace = [], ?string $controller = null): string
    {
        return admin_trans("fields.{$field}", $replace, $controller);
    }
}

if (! function_exists('admin_trans_label')) {
    /**
     * 获取标签翻译
     */
    function admin_trans_label(string $label, array $replace = [], ?string $controller = null): string
    {
        return admin_trans("labels.{$label}", $replace, $controller);
    }
}

if (! function_exists('admin_trans_option')) {
    /**
     * 获取选项翻译
     *
     * @param  string  $value  选项值
     * @param  string  $group  选项组名
     * @param  string|null  $controller  控制器名称
     */
    function admin_trans_option(string $value, string $group, array $replace = [], ?string $controller = null): string
    {
        return admin_trans("options.{$group}.{$value}", $replace, $controller);
    }
}

if (! function_exists('admin_trans_options')) {
    /**
     * 获取选项组数组（用于 ->using() 方法）
     *
     * @param  string  $group  选项组名
     * @param  string|null  $controller  控制器名称
     */
    function admin_trans_options(string $group, ?string $controller = null): array
    {
        $controller = $controller ?? admin_get_controller_name();

        return trans("admin-{$controller}.options.{$group}") ?? [];
    }
}
