<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 备份表组定义
    |--------------------------------------------------------------------------
    |
    | 定义预配置的表组，可以通过 --group 参数快速备份一组表。
    | 每个组可以包含以下配置：
    | - tables: 表名数组，支持通配符（如 'admin_*'）和正则表达式（如 '/^log_/'）
    | - with_structure: 是否包含表结构
    | - compress: 是否压缩备份文件
    | - chunk_size: 分批处理大小（行数）
    |
    */

    'groups' => [
        // 核心业务表
        'core' => [
            'tables' => ['users', 'api_keys', 'channels', 'channel_groups'],
            'with_structure' => true,
            'compress' => true,
        ],

        // 审计日志表
        'audit' => [
            'tables' => ['audit_logs', 'operation_logs'],
            'with_structure' => true,
            'compress' => true,
            'chunk_size' => 10000,
        ],

        // 请求日志表（大表）
        'logs' => [
            'tables' => ['request_logs', 'response_logs', 'channel_request_logs'],
            'with_structure' => false,
            'compress' => true,
            'chunk_size' => 5000,
        ],

        // 管理员相关表
        'admin' => [
            'tables' => ['admin_*'], // 通配符匹配所有 admin_ 开头的表
            'with_structure' => true,
            'compress' => true,
        ],

        // Coding 账户相关表
        'coding' => [
            'tables' => ['coding_*'], // 通配符匹配所有 coding_ 开头的表
            'with_structure' => true,
            'compress' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 默认备份配置
    |--------------------------------------------------------------------------
    |
    | 当命令行未指定参数时使用这些默认值。
    |
    */

    'defaults' => [
        // 备份文件存储路径
        'path' => storage_path('backups/tables'),

        // 是否包含表结构
        'with_structure' => true,

        // 是否压缩备份文件（gzip）
        'compress' => true,

        // 分批处理大小（行数），用于大表处理
        'chunk_size' => 5000,

        // 保留最近 N 个备份文件
        'retention_count' => 10,

        // 备份文件名格式
        // 支持占位符: {table}, {date}, {time}, {timestamp}
        'filename_format' => '{table}_{date}_{time}',
    ],

    /*
    |--------------------------------------------------------------------------
    | 清理策略
    |--------------------------------------------------------------------------
    |
    | 自动清理旧备份文件的配置。
    |
    */

    'cleanup' => [
        // 是否启用自动清理
        'enabled' => true,

        // 默认保留最近 N 个备份
        'retention_count' => 10,

        // 是否在备份完成后立即清理
        'cleanup_after_backup' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 性能优化
    |--------------------------------------------------------------------------
    |
    | 用于处理大表和高负载环境的配置。
    |
    */

    'performance' => [
        // 单次查询最大行数（防止内存溢出）
        'max_rows_per_query' => 10000,

        // 内存限制（MB），超过此值将强制写入文件
        'memory_limit' => 512,

        // 是否使用事务（确保数据一致性）
        'use_transaction' => false,

        // 查询超时时间（秒）
        'query_timeout' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | 排除表
    |--------------------------------------------------------------------------
    |
    | 这些表将不会出现在备份中，即使通过通配符匹配到。
    |
    */

    'exclude_tables' => [
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'failed_jobs',
        'job_batches',
    ],
];
