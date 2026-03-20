<?php

return [
    'title' => 'Audit Logs',

    'fields' => [
        'id' => 'ID',
        'user_id' => 'User ID',
        'username' => 'Username',
        'api_key_id' => 'API Key ID',
        'api_key_name' => 'API Key Name',
        'cached_key_prefix' => 'Cache Key Prefix',

        // Channel info
        'channel_id' => 'Channel ID',
        'channel_name' => 'Channel Name',

        // Request info
        'request_id' => 'Request ID',
        'run_unid' => 'Run UNID',
        'request_type' => 'Request Type',
        'model' => 'Request Model',
        'actual_model' => 'Actual Model',
        'source_protocol' => 'Source Protocol',
        'target_protocol' => 'Target Protocol',

        // Token info
        'prompt_tokens' => 'Prompt Tokens',
        'completion_tokens' => 'Completion Tokens',
        'total_tokens' => 'Total Tokens',
        'cache_read_tokens' => 'Cache Read Tokens',
        'cache_write_tokens' => 'Cache Write Tokens',

        // Cost info
        'cost' => 'Cost',
        'quota' => 'Quota',
        'billing_source' => 'Billing Source',

        // Status info
        'status_code' => 'Status Code',
        'latency_ms' => 'Latency(ms)',
        'first_token_ms' => 'First Token Latency(ms)',

        // Stream info
        'is_stream' => 'Is Stream',
        'finish_reason' => 'Finish Reason',

        // Error info
        'error_type' => 'Error Type',
        'error_message' => 'Error Message',

        // Client info
        'client_ip' => 'Client IP',
        'user_agent' => 'User Agent',
        'group_name' => 'Group Name',

        // Other info
        'channel_affinity' => 'Channel Affinity',
        'metadata' => 'Metadata',

        // Time info
        'created_at' => 'Created At',

        // Grid combined fields
        'api_key_and_affinity' => 'API Key',
        'channel_protocol' => 'Channel/Protocol',
        'model_info' => 'Model',
        'tokens' => 'Tokens',
        'latency' => 'Latency(s)',
        'status_stream' => 'Status/Stream',
    ],

    'labels' => [
        'title' => 'Audit Logs',

        // Grid display text
        'request' => 'Request',
        'upstream' => 'Upstream',
        'total' => 'Total',
        'input' => 'Input',
        'output' => 'Output',
        'cache_read' => 'Cache Read',
        'cache_write' => 'Write',
        'first_token' => 'First Token',
        'total_time' => 'Total',
        'stream' => 'Stream',
        'non_stream' => 'Non-Stream',

        // Tooltips
        'affinity_hit' => 'Channel Affinity Hit',
        'affinity_not_hit' => 'Channel Affinity Not Hit',
    ],

    'options' => [
        'is_stream' => [
            0 => 'No',
            1 => 'Yes',
        ],
    ],
];
