<?php

return [
    'cache_ttl' => env('ROUTER_CACHE_TTL', 60),

    'max_retry' => env('ROUTER_MAX_RETRY', 3),

    'enable_failover' => env('ROUTER_ENABLE_FAILOVER', true),

    'load_balancing' => [
        'default' => env('ROUTER_LB_ALGORITHM', 'weighted_round_robin'),
    ],
];
