<?php

/**
 * 限流配置
 *
 * 控制各级别限流器的默认行为和阈值
 */
return [
    // 全局限流开关
    'enabled' => env('RATE_LIMIT_ENABLED', true),

    // 系统级限流（最后防线）
    'system' => [
        // 是否启用
        'enabled' => env('RATE_LIMIT_SYSTEM_ENABLED', false),
        // 最大请求数
        'max_requests' => env('RATE_LIMIT_SYSTEM_MAX_REQUESTS', 100000),
        // 窗口大小（秒）
        'window_size' => env('RATE_LIMIT_SYSTEM_WINDOW_SIZE', 60),
    ],

    // 渠道限流配置
    'channel' => [
        // 是否启用
        'enabled' => env('RATE_LIMIT_CHANNEL_ENABLED', true),
        // 默认 RPM 限制
        'default_rpm_limit' => env('RATE_LIMIT_CHANNEL_DEFAULT_RPM', 1000),
    ],

    // API Key 限流配置
    'api_key' => [
        // 是否启用
        'enabled' => env('RATE_LIMIT_API_KEY_ENABLED', true),
        // 默认每分钟请求数
        'default_rpm' => env('RATE_LIMIT_API_KEY_DEFAULT_RPM', 60),
        // 默认每日请求数
        'default_rpd' => env('RATE_LIMIT_API_KEY_DEFAULT_RPD', 10000),
        // 默认每日 Token 数
        'default_tpd' => env('RATE_LIMIT_API_KEY_DEFAULT_TPD', 1000000),
    ],

    // Redis 配置
    'redis' => [
        // 连接名
        'connection' => env('RATE_LIMIT_REDIS_CONNECTION', 'default'),
        // Key 前缀
        'key_prefix' => env('RATE_LIMIT_REDIS_PREFIX', 'rl:'),
    ],

    // 使用量日志
    'log_usage' => env('RATE_LIMIT_LOG_USAGE', false),
];
