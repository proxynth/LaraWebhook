<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Custom Validators
    |--------------------------------------------------------------------------
    |
    | Register custom webhook validators for additional services.
    | Each validator must implement WebhookValidatorInterface.
    |
    | Example:
    | 'twilio' => \App\Webhooks\TwilioValidator::class,
    |
    */
    'custom_validators' => [
        // Add your custom validators here
    ],

    'services' => [
        'stripe' => [
            'public' => env('STRIPE_PUBLIC_KEY', 'stripe_public_key_test'),
            'secret' => env('STRIPE_SECRET_KEY', 'stripe_secret_key_test'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', 'stripe_webhook_secret_key_test'),
            'tolerance' => 300,
        ],

        'github' => [
            'webhook_secret' => env('GITHUB_WEBHOOK_SECRET', 'github_webhook_secret_key_test'),
            'tolerance' => 300,
        ],

        'slack' => [
            'webhook_secret' => env('SLACK_WEBHOOK_SECRET', 'slack_webhook_secret_test'),
            'tolerance' => env('SLACK_WEBHOOK_TOLERANCE', 300),
        ],

        'shopify' => [
            'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET', 'shopify_webhook_secret_test'),
            'tolerance' => env('SHOPIFY_WEBHOOK_TOLERANCE', 300),
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

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the webhook dashboard interface.
    |
    */
    'dashboard' => [
        'enabled' => env('LARAWEBHOOK_DASHBOARD_ENABLED', true),
        'path' => env('LARAWEBHOOK_DASHBOARD_PATH', '/larawebhook/dashboard'),
        'middleware' => env('LARAWEBHOOK_DASHBOARD_MIDDLEWARE', 'web'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications Configuration
    |--------------------------------------------------------------------------
    |
    | Configure notifications for repeated webhook failures.
    | Notifications are sent when a webhook fails multiple times consecutively.
    |
    */
    'notifications' => [
        // Enable/disable failure notifications
        'enabled' => env('WEBHOOK_NOTIFICATIONS_ENABLED', false),

        // Notification channels (mail, slack)
        'channels' => array_filter(explode(',', env('WEBHOOK_NOTIFICATION_CHANNELS', 'mail'))),

        // Slack webhook URL (create an Incoming Webhook in your Slack app)
        'slack_webhook' => env('WEBHOOK_SLACK_WEBHOOK_URL'),

        // Email recipients for failure notifications
        'email_recipients' => array_filter(explode(',', env('WEBHOOK_EMAIL_RECIPIENTS', ''))),

        // Number of consecutive failures before sending notification
        'failure_threshold' => (int) env('WEBHOOK_FAILURE_THRESHOLD', 3),

        // Time window in minutes to count failures
        'failure_window_minutes' => (int) env('WEBHOOK_FAILURE_WINDOW', 30),

        // Cooldown in minutes between notifications for the same service/event
        'cooldown_minutes' => (int) env('WEBHOOK_NOTIFICATION_COOLDOWN', 30),
    ],
];
