# LaraWebhook Examples

Real-world code examples showing how to integrate LaraWebhook with popular webhook providers and custom services.

## 📚 Available Examples

### 1. [StripeWebhookController.php](StripeWebhookController.php)
Complete Stripe webhook integration with:
- ✅ Payment intent handling (succeeded/failed)
- ✅ Charge processing
- ✅ Subscription management (create/update/delete)
- ✅ Invoice handling
- ✅ Error handling with automatic retry
- ✅ Comprehensive logging

**Quick Start:**
```php
// routes/web.php
Route::post('/stripe-webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('validate-webhook:stripe');
```

**Configuration:**
```env
STRIPE_WEBHOOK_SECRET=whsec_your_stripe_webhook_secret
```

---

### 2. [GitHubWebhookController.php](GitHubWebhookController.php)
Complete GitHub webhook integration with:
- ✅ Push event handling with auto-deployment
- ✅ Pull request lifecycle (open/close/merge/sync)
- ✅ Issue and comment management
- ✅ Release automation
- ✅ Workflow run monitoring
- ✅ Star and fork tracking
- ✅ Automatic retry on failure

**Quick Start:**
```php
// routes/web.php
Route::post('/github-webhook', [GitHubWebhookController::class, 'handle'])
    ->middleware('validate-webhook:github');
```

**Configuration:**
```env
GITHUB_WEBHOOK_SECRET=your_github_webhook_secret
```

---

### 3. [CustomServiceExample.php](CustomServiceExample.php)
Step-by-step guide for adding custom webhook services (Shopify example):
- ✅ Custom validator creation
- ✅ Signature verification implementation
- ✅ Custom middleware setup
- ✅ Service configuration
- ✅ Controller implementation
- ✅ Testing strategies

**Applies to any service:**
- Shopify
- Mailchimp
- SendGrid
- Twilio
- PayPal
- Square
- And more...

---

## 🚀 Quick Installation

### Step 1: Install LaraWebhook

```bash
composer require proxynth/larawebhook
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="Proxynth\LaraWebhook\LaraWebhookServiceProvider"
```

### Step 3: Configure Services

Add to your `.env`:
```env
STRIPE_WEBHOOK_SECRET=whsec_your_secret_here
GITHUB_WEBHOOK_SECRET=your_github_secret_here
```

Add to `config/larawebhook.php`:
```php
'services' => [
    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
],
```

### Step 4: Run Migrations

```bash
php artisan migrate
```

### Step 5: Copy Example Controllers

Copy the example controller you need to your `app/Http/Controllers/` directory and customize as needed.

---

## 📖 Usage Patterns

### Pattern 1: Using Middleware (Recommended)

```php
// Automatic validation and logging
Route::post('/stripe-webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('validate-webhook:stripe');
```

**What the middleware does:**
- ✅ Validates webhook signature
- ✅ Logs the event to database
- ✅ Returns 403 for invalid signatures
- ✅ Returns 400 for malformed payloads

### Pattern 2: Manual Validation

```php
use Proxynth\Larawebhook\Services\WebhookValidator;

public function handle(Request $request)
{
    $validator = new WebhookValidator(config('larawebhook.services.stripe.webhook_secret'));

    try {
        $log = $validator->validateAndLog(
            $request->getContent(),
            $request->header('Stripe-Signature'),
            'stripe',
            'payment_intent.succeeded'
        );

        // Process the webhook...
    } catch (\Exception $e) {
        return response($e->getMessage(), 403);
    }
}
```

### Pattern 3: Queue Processing (Async)

```php
public function handle(Request $request)
{
    // Validate first (fast)
    $payload = json_decode($request->getContent(), true);
    $event = $payload['type'];

    // Queue processing (async)
    ProcessStripeWebhook::dispatch($event, $payload);

    // Return 200 immediately
    return response()->json(['status' => 'success']);
}
```

---

## 🔒 Security Best Practices

### Always Use HTTPS in Production

```php
// app/Providers/AppServiceProvider.php
if (app()->environment('production')) {
    URL::forceScheme('https');
}
```

### Never Commit Secrets

```bash
# .env (NEVER commit this file)
STRIPE_WEBHOOK_SECRET=whsec_...
GITHUB_WEBHOOK_SECRET=...
```

### Validate All Webhooks

```php
// ALWAYS use the middleware
Route::post('/webhook', [Controller::class, 'handle'])
    ->middleware('validate-webhook:stripe');
```

### Use Try-Catch for External Calls

```php
try {
    Http::timeout(5)->post('external-api', $data);
} catch (\Exception $e) {
    Log::error('External API call failed', ['error' => $e->getMessage()]);
    // Don't throw - webhook should still return 200
}
```

### Set Reasonable Timeouts

```php
set_time_limit(30); // 30 seconds max for webhook processing
```

---

## 🔄 Retry Strategies

### GitHub's Automatic Retry

Return a 5xx error to trigger GitHub's automatic retry:

```php
catch (\Exception $e) {
    Log::error('Webhook processing failed', ['error' => $e->getMessage()]);

    // GitHub will retry up to 3 times with exponential backoff
    return response()->json(['error' => $e->getMessage()], 500);
}
```

### Stripe's Automatic Retry

Stripe retries webhooks automatically for:
- Network failures
- 500-level responses
- Timeouts (30 seconds)

Return 200 to acknowledge receipt, even if processing failed:

```php
catch (\Exception $e) {
    Log::error('Processing failed but webhook acknowledged', [
        'error' => $e->getMessage()
    ]);

    // Return 200 to prevent Stripe retry
    // Handle failure through manual replay or queue
    return response()->json(['status' => 'error'], 200);
}
```

### Manual Replay from Dashboard

Access the dashboard to manually replay failed webhooks:

```
http://your-app.test/larawebhook/dashboard
```

- Filter by status: `failed`
- Click "Replay" to reprocess
- View error messages for debugging

---

## 🧪 Testing Your Webhooks

### Test with Stripe CLI

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Forward webhooks to local dev
stripe listen --forward-to http://localhost:8000/stripe-webhook

# Trigger test events
stripe trigger payment_intent.succeeded
stripe trigger charge.failed
```

### Test with GitHub

1. Go to repository **Settings** → **Webhooks**
2. Click on your webhook
3. Scroll to **Recent Deliveries**
4. Click **Redeliver** to resend

### Test with cURL

```bash
# Stripe webhook
curl -X POST http://localhost:8000/stripe-webhook \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: t=1234567890,v1=signature_here" \
  -d '{
    "type": "payment_intent.succeeded",
    "data": {
      "object": {
        "id": "pi_test_123",
        "amount": 5000,
        "currency": "eur"
      }
    }
  }'

# GitHub webhook
curl -X POST http://localhost:8000/github-webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: push" \
  -H "X-Hub-Signature-256: sha256=signature_here" \
  -d '{
    "ref": "refs/heads/main",
    "commits": [...]
  }'
```

### Test with Tinker

```bash
php artisan tinker
```

```php
// Create test webhook logs
\Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog::factory()
    ->forService('stripe')
    ->successful()
    ->count(10)
    ->create();

// View logs
\Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog::latest()->get();
```

---

## 📊 Monitoring & Debugging

### View Webhook Logs

```bash
php artisan tinker
>>> \Proxynth\LaraWebhook\Models\WebhookLog::where('status', 'failed')
        ->where('created_at', '>', now()->subHour())
        ->get();
```

### Dashboard

Access the web dashboard:
```
http://your-app.test/larawebhook/dashboard
```

**Features:**
- Filter by service, status, date
- View detailed payloads
- Replay failed webhooks
- Monitor success rates

### API Access

```bash
# Get logs via API
curl "http://your-app.test/api/larawebhook/logs?service=stripe&status=failed"

# Replay a webhook
curl -X POST "http://your-app.test/api/larawebhook/logs/123/replay"
```

---

## 🤝 Common Patterns

### Pattern: Auto-Deploy on Release

```php
private function handleRelease(array $payload): void
{
    if ($payload['action'] === 'published') {
        $version = $payload['release']['tag_name'];

        // Queue deployment
        dispatch(new DeployReleaseJob($version));

        // Send notification
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new ReleaseDeployedNotification($version));
    }
}
```

### Pattern: Subscription Management

```php
private function handleSubscriptionCreated(array $payload): void
{
    $subscription = $payload['data']['object'];

    $user = User::where('stripe_customer_id', $subscription['customer'])->first();

    if ($user) {
        $user->update([
            'subscription_status' => 'active',
            'subscription_id' => $subscription['id'],
        ]);

        Mail::to($user->email)->send(new SubscriptionActivatedMail($user));
    }
}
```

### Pattern: Error Notification

```php
catch (\Exception $e) {
    // Log locally
    Log::error('Webhook processing failed', [
        'event' => $event,
        'error' => $e->getMessage(),
    ]);

    // Notify team
    Notification::route('slack', config('services.slack.webhook'))
        ->notify(new WebhookFailedNotification($event, $e));

    // Still return success to prevent retry loops
    return response()->json(['status' => 'error'], 200);
}
```

---

## 💡 Tips & Tricks

### Tip 1: Use Queues for Heavy Processing

```php
// Don't block the webhook response
public function handle(Request $request)
{
    $payload = json_decode($request->getContent(), true);

    // Queue for async processing
    ProcessWebhookJob::dispatch($payload);

    // Return immediately
    return response()->json(['status' => 'success']);
}
```

### Tip 2: Idempotency (Automatic)

LaraWebhook's middleware **automatically handles idempotency**. Duplicate webhooks are rejected before reaching your handler:

```json
// Automatic response for duplicates
{"status": "already_processed", "external_id": "evt_xxx"}
```

If you need to query previous webhooks manually:

```php
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

// Find a specific webhook
$log = WebhookLog::findByExternalId('stripe', 'evt_1234567890');

// Check if exists
$exists = WebhookLog::existsForExternalId('stripe', $eventId);
```

### Tip 3: Staging vs Production

```php
// Different webhooks for different environments
if (app()->environment('production')) {
    $this->processProduction($payload);
} else {
    $this->processStaging($payload);
}
```

---

## 📚 Additional Resources

- **Main Documentation**: [README.md](../README.md)
- **API Documentation**: [Dashboard & API](../README.md#-dashboard--api)
- **Stripe Webhooks**: https://stripe.com/docs/webhooks
- **GitHub Webhooks**: https://docs.github.com/webhooks
- **Webhook Security**: https://webhooks.fyi/security

---

## 🆘 Need Help?

- 📖 Read the [main README](../README.md)
- 🐛 Open an [issue](https://github.com/proxynth/larawebhook/issues)
- 💬 Join the discussion
- 📧 Contact support

---

## 📄 License

These examples are provided under the MIT License as part of LaraWebhook.
