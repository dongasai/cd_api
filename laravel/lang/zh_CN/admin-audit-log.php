<?php

return [
    'title' => '审计日志',

    'fields' => [
        'id' => 'ID',
        'user_id' => '用户ID',
        'username' => '用户名',
        'api_key_id' => 'API Key ID',
        'api_key_name' => 'API Key名称',
        'cached_key_prefix' => '缓存Key前缀',

        // 渠道信息
        'channel_id' => '渠道ID',
        'channel_name' => '渠道名称',

        // 请求信息
        'request_id' => '请求ID',
        'run_unid' => '运行UNID',
        'request_type' => '请求类型',
        'model' => '请求模型',
        'actual_model' => '实际模型',
        'source_protocol' => '请求格式/源协议',
        'target_protocol' => '上游格式/目标协议',

        // Token信息
        'prompt_tokens' => '提示Token数',
        'completion_tokens' => '完成Token数',
        'total_tokens' => '总Token数',
        'cache_read_tokens' => '缓存读取Token数',
        'cache_write_tokens' => '缓存写入Token数',

        // 费用信息
        'cost' => '费用',
        'quota' => '配额',
        'billing_source' => '计费来源',

        // 状态信息
        'status_code' => '状态码',
        'latency_ms' => '延迟(ms)',
        'first_token_ms' => '首Token延迟(ms)',

        // 流式信息
        'is_stream' => '是否流式',
        'finish_reason' => '完成原因',

        // 错误信息
        'error_type' => '错误类型',
        'error_message' => '错误信息',

        // 客户端信息
        'client_ip' => '客户端IP',
        'user_agent' => 'User Agent',
        'group_name' => '分组名称',

        // 其他信息
        'channel_affinity' => '渠道亲和性',
        'metadata' => '元数据',

        // 时间信息
        'created_at' => '创建时间',

        // 列表页组合字段
        'api_key_and_affinity' => 'API Key',
        'channel_protocol' => '渠道/协议',
        'model_info' => '模型',
        'tokens' => 'Token数',
        'latency' => '耗时(s)',
        'status_stream' => '状态码/流',
    ],

    'labels' => [
        'title' => '审计日志',

        // 列表页显示文本
        'request' => '请求',
        'upstream' => '上游',
        'total' => '总',
        'input' => '入',
        'output' => '出',
        'cache_read' => '缓存读',
        'cache_write' => '写',
        'first_token' => '首字',
        'total_time' => '总计',
        'stream' => '流',
        'non_stream' => '非流',

        // 提示信息
        'affinity_hit' => '渠道亲和命中',
        'affinity_not_hit' => '渠道亲和未命中',
    ],

    'options' => [
        'is_stream' => [
            0 => '否',
            1 => '是',
        ],
    ],
];
