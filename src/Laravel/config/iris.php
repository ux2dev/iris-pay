<?php

return [
    'default' => env('IRIS_MERCHANT', 'main'),

    'merchants' => [
        'main' => [
            'public_hash' => env('IRIS_PUBLIC_HASH'),
            'agent_hash' => env('IRIS_AGENT_HASH'),
            'admin_hash' => env('IRIS_ADMIN_HASH'),
            'environment' => env('IRIS_ENVIRONMENT', 'production'),
            'currency' => env('IRIS_CURRENCY', 'EUR'),
            'language' => env('IRIS_LANGUAGE', 'bg'),
        ],
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'iris',
        'middleware' => ['web'],
    ],

    'redirect' => [
        'success' => env('IRIS_REDIRECT_SUCCESS', '/payment/success'),
        'failure' => env('IRIS_REDIRECT_FAILURE', '/payment/failure'),
    ],

    'webhook' => [
        'secret' => env('IRIS_WEBHOOK_SECRET'),
    ],
];
