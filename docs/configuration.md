# Configuration

All configuration is done in `config/larawebhook.php`.

## Services

Configure webhook secrets and tolerance for each service:

```php
'services' => [
    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300, // 5 minutes
    ],
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
    'slack' => [
        'webhook_secret' => env('SLACK_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
    'shopify' => [
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
],
```

### Tolerance

The `tolerance` parameter defines how old a webhook can be (in seconds) before it's rejected. This prevents replay attacks.

- **Stripe**: Uses timestamp in the signature
- **GitHub**: No timestamp validation
- **Slack**: Uses `X-Slack-Request-Timestamp` header
- **Shopify**: No timestamp validation

## Retries

Configure automatic retry behavior for failed webhooks:

```php
'retries' => [
    'enabled' => env('WEBHOOK_RETRIES_ENABLED', true),
    'max_attempts' => env('WEBHOOK_MAX_ATTEMPTS', 3),
    'delays' => [1, 5, 10], // Delays in seconds between retries
    'async' => env('WEBHOOK_ASYNC_RETRIES', false), // Use queue for retries
],
```

### Async Retries

When `async` is `true`:
- Failed validations return `202 Accepted` immediately
- The webhook is queued for background retry via `RetryWebhookJob`
- Provider receives quick response, preventing timeouts

```env
WEBHOOK_RETRIES_ENABLED=true
WEBHOOK_ASYNC_RETRIES=true
WEBHOOK_MAX_ATTEMPTS=3
```

::: tip Queue Configuration
Make sure your queue worker is running to process retry jobs:
```bash
php artisan queue:work
```
:::

## Dashboard

Configure the webhook dashboard:

```php
'dashboard' => [
    'enabled' => env('LARAWEBHOOK_DASHBOARD_ENABLED', true),
    'path' => env('LARAWEBHOOK_DASHBOARD_PATH', '/larawebhook/dashboard'),
    'middleware' => env('LARAWEBHOOK_DASHBOARD_MIDDLEWARE', 'web'),
],
```

### Disable Dashboard

```env
LARAWEBHOOK_DASHBOARD_ENABLED=false
```

### Change Path

```env
LARAWEBHOOK_DASHBOARD_PATH=/admin/webhooks
```

### Add Authentication

```env
LARAWEBHOOK_DASHBOARD_MIDDLEWARE=web,auth
```

Or for admin-only access:

```env
LARAWEBHOOK_DASHBOARD_MIDDLEWARE=web,auth,admin
```

## Notifications

Configure failure notifications:

```php
'notifications' => [
    // Enable/disable failure notifications
    'enabled' => env('WEBHOOK_NOTIFICATIONS_ENABLED', false),

    // Notification channels (mail, slack)
    'channels' => array_filter(explode(',', env('WEBHOOK_NOTIFICATION_CHANNELS', 'mail'))),

    // Slack webhook URL
    'slack_webhook' => env('WEBHOOK_SLACK_WEBHOOK_URL'),

    // Email recipients
    'email_recipients' => array_filter(explode(',', env('WEBHOOK_EMAIL_RECIPIENTS', ''))),

    // Number of consecutive failures before notification
    'failure_threshold' => (int) env('WEBHOOK_FAILURE_THRESHOLD', 3),

    // Time window in minutes to count failures
    'failure_window_minutes' => (int) env('WEBHOOK_FAILURE_WINDOW', 30),

    // Cooldown between notifications
    'cooldown_minutes' => (int) env('WEBHOOK_NOTIFICATION_COOLDOWN', 30),
],
```

See [Failure Notifications](/notifications) for detailed setup.

## Environment Variables Reference

```env
# Services
STRIPE_WEBHOOK_SECRET=whsec_xxx
GITHUB_WEBHOOK_SECRET=xxx
SLACK_WEBHOOK_SECRET=xxx
SHOPIFY_WEBHOOK_SECRET=xxx

# Retries
WEBHOOK_RETRIES_ENABLED=true
WEBHOOK_MAX_ATTEMPTS=3
WEBHOOK_ASYNC_RETRIES=false

# Dashboard
LARAWEBHOOK_DASHBOARD_ENABLED=true
LARAWEBHOOK_DASHBOARD_PATH=/larawebhook/dashboard
LARAWEBHOOK_DASHBOARD_MIDDLEWARE=web

# Notifications
WEBHOOK_NOTIFICATIONS_ENABLED=true
WEBHOOK_NOTIFICATION_CHANNELS=mail,slack
WEBHOOK_EMAIL_RECIPIENTS=admin@example.com
WEBHOOK_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/xxx
WEBHOOK_FAILURE_THRESHOLD=3
WEBHOOK_FAILURE_WINDOW=30
WEBHOOK_NOTIFICATION_COOLDOWN=30
```
