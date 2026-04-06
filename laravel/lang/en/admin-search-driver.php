<?php

return [
    // Field translations
    'fields' => [
        'id' => 'ID',
        'name' => 'Name',
        'slug' => 'Slug',
        'driver_class' => 'Driver Class',
        'config' => 'Configuration',
        'timeout' => 'Timeout',
        'priority' => 'Priority',
        'is_default' => 'Default Driver',
        'status' => 'Status',
        'description' => 'Description',
        'usage_count' => 'Usage Count',
        'last_used_at' => 'Last Used At',
        'error_message' => 'Error Message',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],

    // Label translations
    'labels' => [
        'list' => 'Search Driver List',
        'create' => 'Create Search Driver',
        'edit' => 'Edit Search Driver',
        'detail' => 'Search Driver Detail',
        'search_driver' => 'Search Driver',
    ],

    // Option translations
    'options' => [
        // Status
        'active' => 'Active',
        'inactive' => 'Inactive',
        'error' => 'Error',

        // Driver classes
        'mock' => 'Mock (Simulation)',
        'serper' => 'Serper (Google)',
        'duckduckgo' => 'DuckDuckGo',
    ],

    // Help tips
    'help' => [
        'slug' => 'Unique identifier for configuration reference',
        'driver_class' => 'Select search engine driver implementation class',
        'config' => 'Driver configuration parameters, e.g., api_key, endpoint',
        'timeout' => 'Request timeout in seconds',
        'priority' => 'Priority, higher value means higher priority',
        'is_default' => 'Set as default driver, will be used when not specified',
    ],

    // Action tips
    'actions' => [
        'set_default' => 'Set as Default',
        'set_default_confirm_title' => 'Confirm Set Default Driver',
        'set_default_confirm_message' => 'Will clear other default settings',
        'set_default_success' => 'Set as default driver successfully',
    ],
];
