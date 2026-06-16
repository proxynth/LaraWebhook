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

- ✅ Validates the webhook signature
- ✅ Automatically logs the event to the database
- ✅ Rejects duplicate webhooks (returns `200 OK` with `already_processed`)
- ✅ Returns 403 for invalid signatures
- ✅ Returns 400 for missing headers or malformed payloads

### Manual Validation (Advanced)

For more control, you can manually validate webhooks:

```php
use Proxynth\Larawebhook\Facades\Larawebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Illuminate\Http\Request;

public function handleWebhook(Request $request)
{
    $payload = $request->getContent();
    $signature = Signature::fromString(
        $request->header('Stripe-Signature')
    );

    try {
        // Validate and log in one call
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

## Next Steps

- [Configuration](/configuration) - Full configuration options
- [Services](/services/) - Integration guides for each service
- [Facade API](/facade-api) - Learn the fluent API
