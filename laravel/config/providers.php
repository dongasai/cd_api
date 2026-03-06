<?php

return [

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => 60,
        'connect_timeout' => 10,
        'max_retries' => 3,
        'retry_delay' => 1000,
        'retry_multiplier' => 2.0,
        'circuit_failure_threshold' => 5,
        'circuit_reset_timeout' => 60,
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'timeout' => 60,
        'connect_timeout' => 10,
        'max_retries' => 3,
        'retry_delay' => 1000,
        'retry_multiplier' => 2.0,
        'circuit_failure_threshold' => 5,
        'circuit_reset_timeout' => 60,
    ],

    'azure' => [
        'api_key' => env('AZURE_OPENAI_API_KEY'),
        'base_url' => env('AZURE_OPENAI_ENDPOINT'),
        'deployment_name' => env('AZURE_OPENAI_DEPLOYMENT'),
        'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-02-15-preview'),
        'timeout' => 60,
        'connect_timeout' => 10,
        'max_retries' => 3,
        'retry_delay' => 1000,
        'circuit_failure_threshold' => 5,
        'circuit_reset_timeout' => 60,
    ],

    'compatible' => [

        'deepseek' => [
            'name' => 'deepseek',
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'api_key' => env('DEEPSEEK_API_KEY'),
            'auth_header' => 'Authorization',
            'auth_prefix' => 'Bearer',
            'models' => [
                'deepseek-chat',
                'deepseek-coder',
                'deepseek-reasoner',
            ],
            'timeout' => 60,
            'max_retries' => 3,
        ],

        'zhipu' => [
            'name' => 'zhipu',
            'base_url' => env('ZHIPU_BASE_URL', 'https://open.bigmodel.cn/api/paas/v4'),
            'api_key' => env('ZHIPU_API_KEY'),
            'auth_header' => 'Authorization',
            'auth_prefix' => 'Bearer',
            'models' => [
                'glm-4',
                'glm-4-flash',
                'glm-4-plus',
                'glm-4-air',
            ],
            'timeout' => 60,
            'max_retries' => 3,
        ],

        'moonshot' => [
            'name' => 'moonshot',
            'base_url' => env('MOONSHOT_BASE_URL', 'https://api.moonshot.cn/v1'),
            'api_key' => env('MOONSHOT_API_KEY'),
            'models' => [
                'moonshot-v1-8k',
                'moonshot-v1-32k',
                'moonshot-v1-128k',
            ],
            'timeout' => 60,
            'max_retries' => 3,
        ],

        'ollama' => [
            'name' => 'ollama',
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'api_key' => env('OLLAMA_API_KEY', 'ollama'),
            'models' => [],
            'timeout' => 120,
        ],

        'local' => [
            'name' => 'local',
            'base_url' => env('LOCAL_LLM_BASE_URL'),
            'api_key' => env('LOCAL_LLM_API_KEY', ''),
            'models' => [],
            'timeout' => 120,
        ],
    ],

    'defaults' => [
        'timeout' => 60,
        'connect_timeout' => 10,
        'max_retries' => 3,
        'retry_delay' => 1000,
        'retry_multiplier' => 2.0,
        'circuit_failure_threshold' => 5,
        'circuit_reset_timeout' => 60,
    ],
];
