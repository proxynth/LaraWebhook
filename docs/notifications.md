# Failure Notifications

LaraWebhook can automatically notify you when webhooks fail repeatedly.

## Why Notifications?

- **Detect outages early**: Know immediately when a webhook provider has issues
- **Reduce downtime**: React quickly to integration problems
- **Team collaboration**: Send alerts to Slack channels for instant visibility

## Configuration

```php
// config/larawebhook.php
'notifications' => [
    'enabled' => env('WEBHOOK_NOTIFICATIONS_ENABLED', true),
    'channels' => array_filter(explode(',', env('WEBHOOK_NOTIFICATION_CHANNELS', 'mail'))),
    'slack_webhook' => env('WEBHOOK_SLACK_WEBHOOK_URL'),
    'email_recipients' => array_filter(explode(',', env('WEBHOOK_EMAIL_RECIPIENTS', ''))),
    'failure_threshold' => (int) env('WEBHOOK_FAILURE_THRESHOLD', 3),
    'failure_window_minutes' => (int) env('WEBHOOK_FAILURE_WINDOW', 30),
    'cooldown_minutes' => (int) env('WEBHOOK_NOTIFICATION_COOLDOWN', 30),
],
```

## Environment Variables

```env
# Enable notifications
WEBHOOK_NOTIFICATIONS_ENABLED=true

# Channels: mail, slack (comma-separated)
WEBHOOK_NOTIFICATION_CHANNELS=mail,slack

# Email recipients (comma-separated)
WEBHOOK_EMAIL_RECIPIENTS=admin@example.com,devops@example.com

# Slack incoming webhook URL
WEBHOOK_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Number of failures before alerting (default: 3)
WEBHOOK_FAILURE_THRESHOLD=3

# Time window for counting failures (default: 30 minutes)
WEBHOOK_FAILURE_WINDOW=30

# Cooldown between notifications (default: 30 minutes)
WEBHOOK_NOTIFICATION_COOLDOWN=30
```

## How It Works

```
┌─────────────────┐     ┌──────────────────────┐     ┌─────────────────────┐
│                 │     │                      │     │                     │
│  Webhook Fails  │────▶│  FailureDetector     │────▶│  Send Notification  │
│  (3rd time)     │     │  - Count failures    │     │  - Email            │
│                 │     │  - Check threshold   │     │  - Slack            │
│                 │     │  - Check cooldown    │     │                     │
└─────────────────┘     └──────────────────────┘     └─────────────────────┘
                                  │
                                  │ Below threshold
                                  │ or in cooldown
                                  ▼
                        ┌──────────────────────┐
                        │  No notification     │
                        │  (prevents spam)     │
                        └──────────────────────┘
```

1. **Failure Detection**: Counts consecutive failures for each service/event
2. **Threshold Check**: Only triggers after N failures (configurable)
3. **Time Window**: Only counts failures within the last X minutes
4. **Cooldown**: Prevents spam by waiting between alerts

## Slack Setup

1. Go to [Slack API](https://api.slack.com/apps)
2. Click **Create New App** → **From scratch**
3. Name your app and select workspace
4. Go to **Incoming Webhooks** → Toggle **On**
5. Click **Add New Webhook to Workspace**
6. Select a channel (e.g., `#alerts`)
7. Copy the webhook URL to your `.env`

## Email Setup

Configure Laravel mail in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="LaraWebhook"
```

## Notification Content

### Email

- Subject: `Webhook Failure Alert: {service}`
- Service and event name
- Number of consecutive failures
- Last attempt timestamp
- Error message (if available)
- Link to dashboard

### Slack

- Red alert color
- Service and event details
- Failure count
- Error message
- Direct link to dashboard

## Custom Event Handling

LaraWebhook dispatches an event when a notification is sent:

```php
use Proxynth\Larawebhook\Events\WebhookNotificationSent;

// In EventServiceProvider
protected $listen = [
    WebhookNotificationSent::class => [
        YourCustomListener::class,
    ],
];

// Your listener
class YourCustomListener
{
    public function handle(WebhookNotificationSent $event): void
    {
        // $event->log - The WebhookLog model
        // $event->failureCount - Number of failures

        // Log to external monitoring
        Http::post('https://monitoring.example.com/webhook-failure', [
            'service' => $event->log->service,
            'event' => $event->log->event,
            'failures' => $event->failureCount,
        ]);
    }
}
```

## Preventing Spam

Built-in spam prevention:

1. **Failure Threshold**: Only notifies after N failures (default: 3)
2. **Time Window**: Only counts failures in last X minutes (default: 30)
3. **Cooldown Period**: Won't notify for same service/event within X minutes (default: 30)

**Example scenario:**
- Stripe `payment.failed` fails 3 times in 10 min → **Notification sent**
- 5 more failures in next 20 min → **No notification** (cooldown)
- After 30 min, 3 more failures → **Notification sent again**

## Disable Notifications

```env
WEBHOOK_NOTIFICATIONS_ENABLED=false
```

Or only in production:

```php
'notifications' => [
    'enabled' => env('WEBHOOK_NOTIFICATIONS_ENABLED', app()->environment('production')),
],
```
