<?php

return [
    'services' => [
        'stripe' => [
            'public' => env('STRIPE_PUBLIC_KEY', 'stripe_public_key_test'),
            'secret' => env('STRIPE_SECRET_KEY', 'stripe_secret_key_test'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', 'stripe_webhook_secret_key_test'),
        ],

        'github' => [
            'webhook_secret' => env('GITHUB_WEBHOOK_SECRET', 'github_webhook_secret_key_test'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic retry behavior for failed webhooks.
    | Retries use exponential backoff delays.
    |
    */
    'retries' => [
        'enabled' => env('WEBHOOK_RETRIES_ENABLED', true),
        'max_attempts' => env('WEBHOOK_MAX_ATTEMPTS', 3),
        'delays' => [1, 5, 10], // Delays in seconds between retries
        'async' => env('WEBHOOK_ASYNC_RETRIES', false), // Use queue for retries
    ],
];
