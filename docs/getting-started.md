# Getting Started

## Installation

Install LaraWebhook via Composer:

```bash
composer require proxynth/larawebhook
```

## Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Proxynth\Larawebhook\LarawebhookServiceProvider"
```

This will create `config/larawebhook.php` and run the database migration.

## Configure Secrets

Add your webhook secrets to `.env`:

```env
# Stripe
STRIPE_WEBHOOK_SECRET=whsec_your_stripe_secret

# GitHub
GITHUB_WEBHOOK_SECRET=your_github_secret

# Slack
SLACK_WEBHOOK_SECRET=your_slack_signing_secret

# Shopify
SHOPIFY_WEBHOOK_SECRET=your_shopify_secret
```

## Basic Usage

### Using the Middleware (Recommended)

The easiest way to validate webhooks is using the `validate-webhook` middleware:

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::post('/stripe-webhook', function () {
    // Webhook is automatically validated and logged
    $payload = json_decode(request()->getContent(), true);
    
    // Handle the event
    event(new \App\Events\StripeWebhookReceived($payload));
    
    return response()->json(['status' => 'success']);
})->middleware('validate-webhook:stripe');

Route::post('/github-webhook', function () {
    $payload = json_decode(request()->getContent(), true);
    
    event(new \App\Events\GithubWebhookReceived($payload));
    
    return response()->json(['status' => 'success']);
})->middleware('validate-webhook:github');
```

**What the middleware does:**

- validates the provider signature;
- extracts the event type and provider external id when available;
- resolves an application idempotency key;
- rejects already processed webhooks using `processed_webhook_events`;
- records an audit log in `webhook_logs`;
- records successful processing in `processed_webhook_events`;
- optionally dispatches async retries;
- returns provider-safe HTTP responses.

### Manual Validation (Advanced)

For more control, you can manually validate webhooks:

```php
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Facades\Larawebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Illuminate\Http\Request;

public function handleWebhook(Request $request)
{
    $payload = $request->getContent();
    $signature = Signature::fromString($request->header('Stripe-Signature'));

    try {
        // Validate and log in one call through the application flow
        $log = Larawebhook::validateAndLog(
            $payload,
            $signature,
            'stripe',
            'payment_intent.succeeded'
        );

        // Process the event
        event(new \App\Events\StripeWebhookReceived(json_decode($payload, true)));

        return response()->json(['status' => 'success']);
    } catch (\Exception $e) {
        return response($e->getMessage(), 403);
    }
}
```

`Signature::fromString()` wraps the raw signature header in a typed value object so the application can carry the signature safely and, for providers that need it, keep timestamp metadata attached to the same object.

Manual facade flows are useful when you cannot use the middleware.

For normal inbound webhooks, prefer the middleware because it also handles idempotency, duplicate detection, audit logging and async retry orchestration.

## Architecture Notes

- `ValidateWebhook` validates signatures and normalizes the provider-facing validation result.
- `RecordWebhookLog` is the application entry point for writing audit logs.
- `ReceiveWebhook` orchestrates idempotency, validation, audit logging, and retry decisions.
- `external_id` stores the provider event identifier when one exists.
- `idempotency_key` stores the actual deduplication key, including payload-hash fallback when a provider does not expose a stable external id.
- Dashboard and API reads use read models and read repositories, not application write commands.
- Sync retry happens inline in the request flow; async retry returns `202 Accepted` and queues `RetryWebhookJob`.
- Payload storage can be `none`, `redacted`, or `full`, depending on how much of the raw webhook you want persisted.

## Access the Dashboard

Once installed, access the webhook dashboard at:

```
http://your-app.test/larawebhook/dashboard
```

The dashboard provides:
- 📋 Paginated webhook logs
- 🔍 Filter by service, status, and date
- 👁️ View detailed payloads
- 🔄 Replay failed webhooks

See [Architecture](/architecture) for the full layer and flow breakdown.

## Next Steps

- [Configuration](/configuration) - Full configuration options
- [Services](/services/) - Integration guides for each service
- [Facade API](/facade-api) - Learn the fluent API
