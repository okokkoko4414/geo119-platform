<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'GEO119'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'https://geo119.com'),
    'timezone' => 'Asia/Ho_Chi_Minh',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'available_locales' => [
        'en','vi','zh','es','ar','pt','ru','fr','de','ja',
        'ko','it','nl','pl','sv','da','fi','nb','cs','el',
        'hu','ro','sk','uk','he','tr','th','id','ms','fil',
        'hi','bn','ta','te','mr','gu','kn','ml','pa','ur',
        'fa','sw','am','ha','yo','ig','zu','af','bg','hr',
        'et','lt','lv','sl','sr','is','mk','sq','ka','mn',
        'ne','si','kk','uz','az','lo','km','my','ps','ti',
    ],
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
