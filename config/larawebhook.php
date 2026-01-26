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
        ]
    ]
];
