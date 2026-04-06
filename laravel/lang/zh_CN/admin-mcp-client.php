<?php

return [
    // 字段翻译
    'fields' => [
        'id' => 'ID',
        'name' => '名称',
        'slug' => '标识符',
        'transport' => '传输协议',
        'url' => 'URL',
        'command' => '命令',
        'args' => '参数',
        'headers' => '请求头',
        'timeout' => '超时时间',
        'status' => '状态',
        'last_connected_at' => '最后连接时间',
        'connection_error' => '连接错误',
        'capabilities' => '服务器能力',
        'description' => '描述',
        'created_at' => '创建时间',
        'updated_at' => '更新时间',
    ],

    // 标签翻译
    'labels' => [
        'list' => 'MCP 客户端列表',
        'create' => '创建 MCP 客户端',
        'edit' => '编辑 MCP 客户端',
        'detail' => 'MCP 客户端详情',
        'mcp_client' => 'MCP 客户端',
    ],

    // 选项翻译
    'options' => [
        // 传输协议
        'http' => 'HTTP+SSE',
        'stdio' => 'Stdio',

        // 状态
        'active' => '活跃',
        'inactive' => '未激活',
        'error' => '错误',
    ],

    // 帮助提示
    'help' => [
        'slug' => '唯一标识符，用于 API 调用',
        'url' => 'MCP Server 的 HTTP+SSE 地址',
        'command' => '执行的命令，如 npx、php artisan',
        'args' => '命令参数（JSON 数组格式）',
        'headers' => 'HTTP 请求头，如 Authorization: Bearer sk-xxx',
        'timeout' => '连接超时秒数',
    ],

    // 操作提示
    'actions' => [
        'test_connection' => '测试连接',
        'test_connection_success' => '连接成功',
        'test_connection_failed' => '连接失败',
    ],
];
