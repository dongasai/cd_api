<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 默认搜索驱动
    |--------------------------------------------------------------------------
    |
    | 指定默认使用的搜索引擎驱动。可选值：mock, serper, duckduckgo
    |
    */
    'default' => env('SEARCH_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | 搜索驱动配置
    |--------------------------------------------------------------------------
    |
    | 各驱动的详细配置。每个驱动可以有自己的配置项。
    |
    */
    'drivers' => [
        'mock' => [
            'driver' => \App\Services\Search\Driver\MockSearchDriver::class,
        ],

        'serper' => [
            'driver' => \App\Services\Search\Driver\SerperSearchDriver::class,
            'api_key' => env('SERPER_API_KEY'),
            'timeout' => 30,
        ],

        'duckduckgo' => [
            'driver' => \App\Services\Search\Driver\DuckDuckGoSearchDriver::class,
            'timeout' => 30,
        ],
    ],
];
