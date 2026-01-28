# Slack Integration

## Configuration

Add your Slack signing secret to `.env`:

```env
SLACK_WEBHOOK_SECRET=your_slack_signing_secret_here
```

Get your signing secret:
1. Go to [Slack API](https://api.slack.com/apps)
2. Select your app
3. Go to **Basic Information** → **App Credentials**
4. Copy the **Signing Secret**

## Route Setup

```php
// routes/web.php
use App\Http\Controllers\SlackWebhookController;

Route::post('/slack-webhook', [SlackWebhookController::class, 'handle'])
    ->middleware('validate-webhook:slack');
```

## Controller Example

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SlackWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        // Handle URL verification challenge
        if (isset($payload['type']) && $payload['type'] === 'url_verification') {
            return response()->json(['challenge' => $payload['challenge']]);
        }

        $eventType = $payload['event']['type'] ?? $payload['type'] ?? 'unknown';

        match ($eventType) {
            'app_mention' => $this->handleAppMention($payload),
            'message' => $this->handleMessage($payload),
            'block_actions' => $this->handleBlockActions($payload),
            'view_submission' => $this->handleViewSubmission($payload),
            default => $this->handleUnknown($eventType),
        };

        return response()->json(['status' => 'success']);
    }

    private function handleAppMention(array $payload): void
    {
        $event = $payload['event'];

        Log::info('Slack: App mentioned', [
            'user' => $event['user'],
            'channel' => $event['channel'],
            'text' => $event['text'],
        ]);

        // Reply to the mention
        // $this->slackClient->chat->postMessage([
        //     'channel' => $event['channel'],
        //     'text' => "Hi <@{$event['user']}>! How can I help?",
        // ]);
    }

    private function handleMessage(array $payload): void
    {
        $event = $payload['event'];

        // Ignore bot messages to prevent loops
        if (isset($event['bot_id'])) {
            return;
        }

        Log::info('Slack: Message received', [
            'channel' => $event['channel'],
            'user' => $event['user'] ?? 'unknown',
        ]);
    }

    private function handleBlockActions(array $payload): void
    {
        $action = $payload['actions'][0] ?? [];

        Log::info('Slack: Block action triggered', [
            'action_id' => $action['action_id'] ?? 'unknown',
            'user' => $payload['user']['id'] ?? 'unknown',
        ]);
    }

    private function handleViewSubmission(array $payload): void
    {
        Log::info('Slack: View submitted', [
            'view_id' => $payload['view']['id'] ?? 'unknown',
            'user' => $payload['user']['id'] ?? 'unknown',
        ]);
    }

    private function handleUnknown(string $eventType): void
    {
        Log::warning('Slack: Unknown event', ['event_type' => $eventType]);
    }
}
```

## Signature Validation

Slack sends two headers:
- `X-Slack-Signature`: `v0=HMAC_SHA256_SIGNATURE`
- `X-Slack-Request-Timestamp`: Unix timestamp

The signature is computed from: `v0:timestamp:body`

LaraWebhook automatically:
1. Combines the timestamp and signature
2. Validates the timestamp against tolerance
3. Verifies the HMAC-SHA256 signature

## URL Verification

When you first configure your webhook URL, Slack sends a verification challenge:

```json
{
    "type": "url_verification",
    "challenge": "random_string"
}
```

The controller example above handles this automatically by returning the challenge.

## Configure in Slack

### Event Subscriptions

1. Go to [Slack API](https://api.slack.com/apps)
2. Select your app
3. Navigate to **Event Subscriptions**
4. Enable events
5. Enter Request URL: `https://your-domain.com/slack-webhook`
6. Subscribe to events (e.g., `app_mention`, `message.channels`)
7. Save changes

### Interactivity

For interactive components (buttons, modals):

1. Navigate to **Interactivity & Shortcuts**
2. Enable Interactivity
3. Enter Request URL: `https://your-domain.com/slack-webhook`
4. Save changes

## Common Events

| Event | Description |
|-------|-------------|
| `app_mention` | Bot mentioned in channel |
| `message` | Message in channel |
| `message.im` | Direct message to bot |
| `block_actions` | Button/menu clicked |
| `view_submission` | Modal submitted |
| `app_home_opened` | User opened app home |
