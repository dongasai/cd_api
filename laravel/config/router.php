<?php

return [
    'cache_ttl' => env('ROUTER_CACHE_TTL', 60),

    'max_retry' => env('ROUTER_MAX_RETRY', 3),

    'enable_failover' => env('ROUTER_ENABLE_FAILOVER', true),

    'load_balancing' => [
        'default' => env('ROUTER_LB_ALGORITHM', 'weighted_round_robin'),
    ],

    'health_check' => [
        'enabled' => env('ROUTER_HEALTH_CHECK_ENABLED', true),
        'interval' => env('ROUTER_HEALTH_CHECK_INTERVAL', 300),
        'failure_threshold' => env('ROUTER_FAILURE_THRESHOLD', 5),
        'success_threshold' => env('ROUTER_SUCCESS_THRESHOLD', 3),
    ],
];
