<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'translations-tier1' => [
                'connection' => 'redis',
                'queue' => ['translations-tier1'],
                'balance' => 'auto',
                'minProcesses' => 2,
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 120,
                'memory' => 256,
            ],
            'translations-tier2' => [
                'connection' => 'redis',
                'queue' => ['translations-tier2'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 6,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 120,
                'memory' => 256,
            ],
            'translations-tier3' => [
                'connection' => 'redis',
                'queue' => ['translations-tier3'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 120,
                'memory' => 128,
            ],
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries' => 3,
                'timeout' => 60,
                'memory' => 128,
            ],
        ],

        'dev' => [
            'translations-tier1' => [
                'connection' => 'redis',
                'queue' => ['translations-tier1'],
                'balance' => 'simple',
                'processes' => 2,
                'tries' => 3,
                'timeout' => 120,
                'memory' => 256,
            ],
            'translations-tier2' => [
                'connection' => 'redis',
                'queue' => ['translations-tier2'],
                'balance' => 'simple',
                'processes' => 1,
                'tries' => 3,
                'timeout' => 120,
                'memory' => 256,
            ],
            'translations-tier3' => [
                'connection' => 'redis',
                'queue' => ['translations-tier3'],
                'balance' => 'simple',
                'processes' => 1,
                'tries' => 3,
                'timeout' => 120,
                'memory' => 128,
            ],
        ],
    ],

];
