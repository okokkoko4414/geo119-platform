<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | DeepSeek via claude_local
    |--------------------------------------------------------------------------
    */

    'deepseek' => [
        'endpoint' => env('DEEPSEEK_ENDPOINT', 'http://claude-local:8080'),
        'api_key' => env('DEEPSEEK_API_KEY', ''),
        'timeout' => env('DEEPSEEK_TIMEOUT', 30),
    ],

];
