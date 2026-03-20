<?php

return [
    'fields' => [
        'id' => 'ID',
        'name' => '名称',
        'key' => '密钥',
        'status' => '状态',
        'model_mappings' => '模型映射',
        'allowed_channels' => '允许的渠道',
        'not_allowed_channels' => '禁止的渠道',
        'rate_limit' => '速率限制',
        'expires_at' => '过期时间',
        'last_used_at' => '最后使用时间',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',

        // 速率限制子字段
        'requests_per_minute' => '每分钟请求数',
        'requests_per_day' => '每日请求数',
        'tokens_per_day' => '每日Token数',

        // 详情页特殊字段
        'available_models' => '可用模型',
    ],

    'labels' => [
        'title' => 'API密钥管理',
        'key_help' => 'API密钥，创建后请妥善保存',
        'name_help' => 'API密钥的名称，方便识别',
        'allowed_channels_help' => '选择允许访问的渠道，留空表示不限制',
        'not_allowed_channels_help' => '选择禁止访问的渠道',
        'rate_limit_help' => '配置API密钥的速率限制',
        'requests_per_minute_help' => '每分钟最大请求数，0表示不限制',
        'requests_per_day_help' => '每日最大请求数，0表示不限制',
        'tokens_per_day_help' => '每日最大Token数，0表示不限制',
        'expires_at_help' => '留空表示永不过期',
        'model_mappings_help' => '配置模型别名映射，格式：别名 => 实际模型名。例如：cd-coding-latest => gpt-4',

        // 显示文本
        'no_limit' => '不限制',
        'none' => '无',
        'no_available_models' => '无可用模型',
        'times_per_minute' => '次/分钟',
        'times_per_day' => '次/天',
        'tokens_per_day' => 'Token/天',
        'actual_model' => '实际模型',
    ],

    'options' => [
        'status' => [
            'active' => '激活',
            'revoked' => '已撤销',
            'expired' => '已过期',
        ],
    ],
];
