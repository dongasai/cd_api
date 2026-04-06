<?php

return [
    // 字段翻译
    'fields' => [
        'id' => 'ID',
        'name' => '名称',
        'slug' => '标识符',
        'driver_class' => '驱动类',
        'config' => '配置',
        'timeout' => '超时时间',
        'priority' => '优先级',
        'is_default' => '默认驱动',
        'status' => '状态',
        'description' => '描述',
        'usage_count' => '使用次数',
        'last_used_at' => '最后使用时间',
        'error_message' => '错误信息',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],

    // 标签翻译
    'labels' => [
        'list' => '搜索驱动列表',
        'create' => '创建搜索驱动',
        'edit' => '编辑搜索驱动',
        'detail' => '搜索驱动详情',
        'search_driver' => '搜索驱动',
    ],

    // 选项翻译
    'options' => [
        // 状态
        'active' => '活跃',
        'inactive' => '未激活',
        'error' => '错误',

        // 驱动类
        'mock' => 'Mock (模拟)',
        'serper' => 'Serper (Google)',
        'duckduckgo' => 'DuckDuckGo',
    ],

    // 帮助提示
    'help' => [
        'slug' => '唯一标识符，用于配置引用',
        'driver_class' => '选择搜索引擎驱动实现类',
        'config' => '驱动配置参数，如 api_key、endpoint 等',
        'timeout' => '请求超时秒数',
        'priority' => '优先级，数值越大优先级越高',
        'is_default' => '设为默认驱动后，未指定驱动时自动使用',
    ],

    // 操作提示
    'actions' => [
        'set_default' => '设为默认',
        'set_default_confirm_title' => '确认设置默认驱动',
        'set_default_confirm_message' => '将清除其他默认设置',
        'set_default_success' => '已设置为默认驱动',
    ],
];
