<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | These are the locales that are available for the application.
    | The key is the locale code, and the value contains the label
    | and flag for display purposes.
    |
    */
    'available' => [
        'zh_CN' => [
            'label' => '简体中文',
            'flag' => '🇨🇳',
            'name' => 'Chinese Simplified',
        ],
        'en' => [
            'label' => 'English',
            'flag' => '🇬🇧',
            'name' => 'English',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale that will be used by the application.
    |
    */
    'default' => env('APP_LOCALE', 'zh_CN'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | This is the fallback locale that will be used when the current
    | locale does not have a translation for a given string.
    |
    */
    'fallback' => env('APP_FALLBACK_LOCALE', 'zh_CN'),
];
