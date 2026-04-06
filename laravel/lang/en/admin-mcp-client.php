<?php

return [
    // Field translations
    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'slug' => 'Slug',
        'transport' => 'Transport Protocol',
        'url' => 'URL',
        'command' => 'Command',
        'args' => 'Arguments',
        'headers' => 'Headers',
        'timeout' => 'Timeout',
        'status' => 'Status',
        'last_connected_at' => 'Last Connected At',
        'connection_error' => 'Connection Error',
        'capabilities' => 'Server Capabilities',
        'description' => 'Description',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    // Label translations
    'labels' => [
        'list' => 'MCP Client List',
        'create' => 'Create MCP Client',
        'edit' => 'Edit MCP Client',
        'detail' => 'MCP Client Detail',
        'mcp_client' => 'MCP Client',
    ],

    // Option translations
    'options' => [
        // Transport protocols
        'http' => 'HTTP+SSE',
        'stdio' => 'Stdio',

        // Status
        'active' => 'Active',
        'inactive' => 'Inactive',
        'error' => 'Error',
    ],

    // Help tips
    'help' => [
        'slug' => 'Unique identifier for API calls',
        'url' => 'MCP Server HTTP+SSE address',
        'command' => 'Command to execute, e.g., npx, php artisan',
        'args' => 'Command arguments (JSON array format)',
        'headers' => 'HTTP request headers, e.g., Authorization: Bearer sk-xxx',
        'timeout' => 'Connection timeout in seconds',
    ],

    // Action tips
    'actions' => [
        'test_connection' => 'Test Connection',
        'test_connection_success' => 'Connection Successful',
        'test_connection_failed' => 'Connection Failed',
    ],
];
