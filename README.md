# LaraWebhook 🚀

[![Latest Version](https://img.shields.io/packagist/v/proxynth/larawebhook.svg)](https://packagist.org/packages/proxynth/larawebhook)
[![Tests](https://github.com/proxynth/larawebhook/actions/workflows/tests.yml/badge.svg)](https://github.com/proxynth/larawebhook/actions)
[![Codecov](https://codecov.io/github/proxynth/LaraWebhook/graph/badge.svg?token=4WGFTA8HDR)](https://codecov.io/github/proxynth/LaraWebhook)
[![PHPStan](https://github.com/proxynth/larawebhook/actions/workflows/phpstan.yml/badge.svg)](https://github.com/proxynth/larawebhook/actions)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**LaraWebhook** is an open-source Laravel package for handling incoming webhooks in a **secure, reliable, and simple** way. Validate signatures, manage retries, log events, and integrate popular services (Stripe, GitHub, Slack, etc.) in minutes.

---

## ✨ Features

- **Signature Validation**: Verify webhook authenticity (Stripe, GitHub, Slack, Shopify)
- **Retry Management**: Automatically retry failed webhooks with exponential backoff
- **Detailed Logging**: Store events and errors for debugging
- **Failure Notifications**: Get alerted via Email and Slack when webhooks fail repeatedly
- **Interactive Dashboard**: Modern UI with Alpine.js and Tailwind CSS for log management
- **REST API**: Programmatic access to webhook logs with filtering and pagination
- **Replay Webhooks**: Re-process failed webhooks from dashboard or API
- **Fluent Facade API**: Simple and expressive API via `Larawebhook` facade
- **Type-Safe Services**: `WebhookService` enum for IDE autocompletion and type safety
- **Easy Integration**: Minimal configuration, compatible with Laravel 9+
- **Extensible Architecture**: Strategy Pattern for parsers and validators - add new services in minutes

---

## 📦 Installation

1. Install the package via Composer:
   ```bash
   composer require proxynth/larawebhook
   ```

2. Publish the configuration:
   ```bash
   php artisan vendor:publish --provider="Proxynth\LaraWebhook\LaraWebhookServiceProvider"
   ```

3. Configure your signature keys in `config/larawebhook.php`:
   ```php
   'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300, // Tolerance in seconds
   ],
   ```

---

## 🛠 Usage

### Using the Middleware (Recommended)

The easiest way to validate webhooks is using the `validate-webhook` middleware:

```php
// routes/web.php
Route::post('/stripe-webhook', function () {
    // Webhook is automatically validated and logged
    // Process your webhook here
    $payload = json_decode(request()->getContent(), true);

    // Handle the event
    event(new \App\Events\StripeWebhookReceived($payload));

    return response()->json(['status' => 'success']);
})->middleware('validate-webhook:stripe');

Route::post('/github-webhook', function () {
    // Webhook is automatically validated and logged
    $payload = json_decode(request()->getContent(), true);

    // Handle the event
    event(new \App\Events\GithubWebhookReceived($payload));

    return response()->json(['status' => 'success']);
})->middleware('validate-webhook:github');
```

**What the middleware does:**
- ✅ Validates the webhook signature
- ✅ Automatically logs the event to the database
- ✅ Returns 403 for invalid signatures
- ✅ Returns 400 for missing headers or malformed payloads

### Manual Validation (Advanced)

For more control, you can manually validate webhooks:

```php
// app/Http/Controllers/WebhookController.php
use Proxynth\Larawebhook\Services\WebhookValidator;
use Illuminate\Http\Request;

public function handleWebhook(Request $request)
{
    $payload = $request->getContent();
    $signature = $request->header('Stripe-Signature');
    $secret = config('larawebhook.services.stripe.webhook_secret');

    $validator = new WebhookValidator($secret);

    try {
        // Validate and log in one call
        $log = $validator->validateAndLog(
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

---

## 🎯 Facade & Enum API

LaraWebhook provides a powerful Facade and an Enum for type-safe service handling.

### Using the Facade

The `Larawebhook` facade provides a fluent API for all webhook operations:

```php
use Proxynth\Larawebhook\Facades\Larawebhook;

// Validate a webhook
Larawebhook::validate($payload, $signature, 'stripe');

// Validate and log
$log = Larawebhook::validateAndLog($payload, $signature, 'github', 'push');

// Log webhooks manually
Larawebhook::logSuccess('stripe', 'payment.succeeded', $payload);
Larawebhook::logFailure('stripe', 'payment.failed', $payload, 'Card declined');

// Query logs
$allLogs = Larawebhook::logs();
$stripeLogs = Larawebhook::logsForService('stripe');
$failedLogs = Larawebhook::failedLogs();
$successLogs = Larawebhook::successfulLogs();

// Notifications
Larawebhook::sendNotificationIfNeeded('stripe', 'payment.failed');
Larawebhook::notificationsEnabled(); // true/false
Larawebhook::getNotificationChannels(); // ['mail', 'slack']

// Configuration helpers
Larawebhook::getSecret('stripe'); // Returns webhook secret
Larawebhook::isServiceSupported('stripe'); // true
Larawebhook::supportedServices(); // ['stripe', 'github']
```

### WebhookService Enum

The `WebhookService` enum centralizes all service-related configuration:

```php
use Proxynth\Larawebhook\Enums\WebhookService;

// Available services
WebhookService::Stripe; // 'stripe'
WebhookService::Github; // 'github'

// Get signature header for a service
WebhookService::Stripe->signatureHeader(); // 'Stripe-Signature'
WebhookService::Github->signatureHeader(); // 'X-Hub-Signature-256'

// Get secret from config
WebhookService::Stripe->secret(); // Returns configured secret

// Get the payload parser (for extracting event types and metadata)
WebhookService::Stripe->parser(); // StripePayloadParser
WebhookService::Github->parser(); // GithubPayloadParser

// Get the signature validator (for verifying webhook authenticity)
WebhookService::Stripe->signatureValidator(); // StripeSignatureValidator
WebhookService::Github->signatureValidator(); // GithubSignatureValidator

// Check if a service is supported
WebhookService::isSupported('stripe'); // true
WebhookService::isSupported('unknown'); // false

// Convert from string
$service = WebhookService::tryFromString('stripe'); // WebhookService::Stripe
$service = WebhookService::fromString('stripe'); // WebhookService::Stripe (throws on invalid)

// Get all values (useful for validation rules)
WebhookService::values(); // ['stripe', 'github']
WebhookService::validationRule(); // ['stripe', 'github']
```

### Using Enum with Facade

All facade methods accept both strings and the enum:

```php
use Proxynth\Larawebhook\Facades\Larawebhook;
use Proxynth\Larawebhook\Enums\WebhookService;

// Both are equivalent
Larawebhook::validate($payload, $signature, 'stripe');
Larawebhook::validate($payload, $signature, WebhookService::Stripe);

// Type-safe service handling
$service = WebhookService::Stripe;
$log = Larawebhook::validateAndLog($payload, $signature, $service, 'payment.succeeded');
```

### Benefits of Using the Enum

- **Type Safety**: IDE autocompletion and static analysis support
- **Centralized Configuration**: All service-related config in one place
- **DRY Principle**: No more duplicated service strings across the codebase
- **Easy Extension**: Add a new service by adding a case to the enum

---

## 🏗️ Extensible Architecture

LaraWebhook uses the **Strategy Pattern** for maximum extensibility. Each webhook service has its own:

- **PayloadParser**: Extracts event types and metadata from the webhook payload
- **SignatureValidator**: Validates the webhook signature according to the provider's format

### Architecture Overview

```
src/
├── Contracts/
│   ├── PayloadParserInterface.php        # Strategy interface for parsing
│   └── SignatureValidatorInterface.php   # Strategy interface for validation
├── Parsers/
│   ├── StripePayloadParser.php           # Stripe payload parsing
│   └── GithubPayloadParser.php           # GitHub payload parsing
├── Validators/
│   ├── StripeSignatureValidator.php      # Stripe signature validation
│   └── GithubSignatureValidator.php      # GitHub signature validation
└── Enums/
    └── WebhookService.php                # Central delegation point
```

### Adding a New Service (Example: PayPal)

Adding a new webhook service requires just 4 steps:

**Step 1: Create the Payload Parser**

```php
// src/Parsers/PaypalPayloadParser.php
namespace Proxynth\Larawebhook\Parsers;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;

class PaypalPayloadParser implements PayloadParserInterface
{
    public function extractEventType(array $data): string
    {
        return $data['event_type'] ?? 'unknown';
    }

    public function extractMetadata(array $data): array
    {
        return [
            'event_id' => $data['id'] ?? null,
            'resource_type' => $data['resource_type'] ?? null,
            'summary' => $data['summary'] ?? null,
        ];
    }

    public function serviceName(): string
    {
        return 'paypal';
    }
}
```

**Step 2: Create the Signature Validator**

```php
// src/Validators/PaypalSignatureValidator.php
namespace Proxynth\Larawebhook\Validators;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;

class PaypalSignatureValidator implements SignatureValidatorInterface
{
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        // PayPal uses base64-encoded HMAC-SHA256
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (! hash_equals($expected, $signature)) {
            throw new InvalidSignatureException('Invalid PayPal webhook signature.');
        }

        return true;
    }

    public function serviceName(): string
    {
        return 'paypal';
    }
}
```

**Step 3: Register in the Enum**

```php
// src/Enums/WebhookService.php
enum WebhookService: string
{
    case Stripe = 'stripe';
    case Github = 'github';
    case Paypal = 'paypal';  // Add the new case

    public function parser(): PayloadParserInterface
    {
        return match ($this) {
            self::Stripe => new StripePayloadParser,
            self::Github => new GithubPayloadParser,
            self::Paypal => new PaypalPayloadParser,  // Add mapping
        };
    }

    public function signatureValidator(): SignatureValidatorInterface
    {
        return match ($this) {
            self::Stripe => new StripeSignatureValidator,
            self::Github => new GithubSignatureValidator,
            self::Paypal => new PaypalSignatureValidator,  // Add mapping
        };
    }

    public function signatureHeader(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe-Signature',
            self::Github => 'X-Hub-Signature-256',
            self::Paypal => 'PAYPAL-TRANSMISSION-SIG',  // Add header
        };
    }
}
```

**Step 4: Add Configuration**

```php
// config/larawebhook.php
'services' => [
    'paypal' => [
        'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
],
```

That's it! Your new service is now fully integrated:

```php
// Use with middleware
Route::post('/paypal-webhook', [PaypalController::class, 'handle'])
    ->middleware('validate-webhook:paypal');

// Or with the facade
Larawebhook::validate($payload, $signature, WebhookService::Paypal);
```

### Using Parsers Directly

You can access parsers directly for custom payload processing:

```php
use Proxynth\Larawebhook\Enums\WebhookService;

$payload = json_decode($request->getContent(), true);

// Extract event type
$eventType = WebhookService::Stripe->parser()->extractEventType($payload);
// Returns: 'payment_intent.succeeded'

// Extract metadata
$metadata = WebhookService::Github->parser()->extractMetadata($payload);
// Returns: ['delivery_id' => '...', 'action' => 'opened', 'sender' => 'octocat', ...]
```

### Using Validators Directly

For advanced use cases, you can use validators directly:

```php
use Proxynth\Larawebhook\Enums\WebhookService;

$isValid = WebhookService::Stripe->signatureValidator()->validate(
    payload: $rawPayload,
    signature: $signatureHeader,
    secret: config('larawebhook.services.stripe.webhook_secret'),
    tolerance: 300
);
```

---

## 🔌 Service Integration Examples

Complete integration guides with real-world examples for popular webhook providers.

### 🔵 Stripe Integration

#### 1. Configuration

Add your Stripe webhook secret to `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_your_stripe_webhook_secret_here
```

Then configure the service in `config/larawebhook.php`:

```php
'services' => [
    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300, // 5 minutes tolerance for timestamp validation
    ],
],
```

#### 2. Create Route and Controller

**Define the webhook route** in `routes/web.php`:

```php
use App\Http\Controllers\StripeWebhookController;

Route::post('/stripe-webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('validate-webhook:stripe');
```

**Create the controller** at `app/Http/Controllers/StripeWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Webhook is already validated by the middleware
        $payload = json_decode($request->getContent(), true);
        $event = $payload['type'] ?? 'unknown';

        // Route to specific event handlers
        match ($event) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($payload),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($payload),
            'charge.succeeded' => $this->handleChargeSucceeded($payload),
            'charge.failed' => $this->handleChargeFailed($payload),
            'customer.subscription.created' => $this->handleSubscriptionCreated($payload),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($payload),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($payload),
            'invoice.paid' => $this->handleInvoicePaid($payload),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($payload),
            default => $this->handleUnknownEvent($event, $payload),
        };

        return response()->json(['status' => 'success']);
    }

    private function handlePaymentIntentSucceeded(array $payload): void
    {
        $paymentIntent = $payload['data']['object'];

        Log::info('Stripe: Payment intent succeeded', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount'],
            'currency' => $paymentIntent['currency'],
            'customer' => $paymentIntent['customer'],
        ]);

        // Example: Update order status in your database
        // Order::where('stripe_payment_intent_id', $paymentIntent['id'])
        //     ->update(['status' => 'paid']);
    }

    private function handlePaymentIntentFailed(array $payload): void
    {
        $paymentIntent = $payload['data']['object'];

        Log::error('Stripe: Payment intent failed', [
            'payment_intent_id' => $paymentIntent['id'],
            'last_payment_error' => $paymentIntent['last_payment_error'],
        ]);

        // Example: Notify customer of payment failure
        // $order = Order::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
        // Mail::to($order->customer->email)->send(new PaymentFailedMail($order));
    }

    private function handleChargeSucceeded(array $payload): void
    {
        $charge = $payload['data']['object'];

        Log::info('Stripe: Charge succeeded', [
            'charge_id' => $charge['id'],
            'amount' => $charge['amount'],
        ]);
    }

    private function handleChargeFailed(array $payload): void
    {
        $charge = $payload['data']['object'];

        Log::error('Stripe: Charge failed', [
            'charge_id' => $charge['id'],
            'failure_message' => $charge['failure_message'],
        ]);
    }

    private function handleSubscriptionCreated(array $payload): void
    {
        $subscription = $payload['data']['object'];

        Log::info('Stripe: Subscription created', [
            'subscription_id' => $subscription['id'],
            'customer' => $subscription['customer'],
            'status' => $subscription['status'],
        ]);

        // Example: Grant access to premium features
        // User::where('stripe_customer_id', $subscription['customer'])
        //     ->update(['subscription_status' => 'active']);
    }

    private function handleSubscriptionUpdated(array $payload): void
    {
        $subscription = $payload['data']['object'];

        Log::info('Stripe: Subscription updated', [
            'subscription_id' => $subscription['id'],
            'status' => $subscription['status'],
        ]);
    }

    private function handleSubscriptionDeleted(array $payload): void
    {
        $subscription = $payload['data']['object'];

        Log::info('Stripe: Subscription deleted', [
            'subscription_id' => $subscription['id'],
        ]);

        // Example: Revoke access to premium features
        // User::where('stripe_customer_id', $subscription['customer'])
        //     ->update(['subscription_status' => 'cancelled']);
    }

    private function handleInvoicePaid(array $payload): void
    {
        $invoice = $payload['data']['object'];

        Log::info('Stripe: Invoice paid', [
            'invoice_id' => $invoice['id'],
            'amount_paid' => $invoice['amount_paid'],
        ]);
    }

    private function handleInvoicePaymentFailed(array $payload): void
    {
        $invoice = $payload['data']['object'];

        Log::error('Stripe: Invoice payment failed', [
            'invoice_id' => $invoice['id'],
            'attempt_count' => $invoice['attempt_count'],
        ]);
    }

    private function handleUnknownEvent(string $event, array $payload): void
    {
        Log::warning('Stripe: Unknown event type received', [
            'event_type' => $event,
        ]);
    }
}
```

#### 3. Webhook Flow Diagram

```
┌─────────────────┐         ┌──────────────────────┐         ┌─────────────────────┐
│                 │         │                      │         │                     │
│  Stripe Server  │────────▶│  LaraWebhook         │────────▶│  Your Application   │
│                 │  POST   │  - Validates         │  Valid  │  - Process event    │
│  (Webhook)      │         │    signature         │         │  - Update database  │
│                 │         │  - Logs event        │         │  - Send emails      │
└─────────────────┘         │  - Returns response  │         │                     │
                            └──────────────────────┘         └─────────────────────┘
                                      │
                                      │ Invalid signature
                                      ▼
                            ┌──────────────────────┐
                            │  Returns 403         │
                            │  Forbidden           │
                            └──────────────────────┘
```

#### 4. Example Log Entry

Successful webhook processing creates a log entry:

```json
{
  "id": 1,
  "service": "stripe",
  "event": "payment_intent.succeeded",
  "status": "success",
  "payload": {
    "id": "evt_1234567890",
    "type": "payment_intent.succeeded",
    "data": {
      "object": {
        "id": "pi_1234567890",
        "amount": 5000,
        "currency": "usd",
        "customer": "cus_1234567890",
        "status": "succeeded"
      }
    }
  },
  "attempt": 0,
  "error_message": null,
  "created_at": "2024-01-15 10:30:00"
}
```

#### 5. Configure Webhook in Stripe Dashboard

1. Go to [Stripe Dashboard](https://dashboard.stripe.com/webhooks)
2. Click **Add endpoint**
3. Enter your webhook URL: `https://your-domain.com/stripe-webhook`
4. Select events to listen for (or select "receive all events")
5. Copy the **Signing secret** (starts with `whsec_`) and add it to your `.env` file

#### 6. Testing & Debugging

**View webhook logs:**
```bash
php artisan tinker
>>> \Proxynth\LaraWebhook\Models\WebhookLog::where('service', 'stripe')->latest()->first();
```

**Test with Stripe CLI:**
```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Forward webhooks to your local environment
stripe listen --forward-to http://localhost:8000/stripe-webhook

# Trigger a test webhook
stripe trigger payment_intent.succeeded
```

---

### ⚫ GitHub Integration

#### 1. Configuration

Add your GitHub webhook secret to `.env`:

```env
GITHUB_WEBHOOK_SECRET=your_github_webhook_secret_here
```

Then configure the service in `config/larawebhook.php`:

```php
'services' => [
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
],
```

#### 2. Create Route and Controller

**Define the webhook route** in `routes/web.php`:

```php
use App\Http\Controllers\GitHubWebhookController;

Route::post('/github-webhook', [GitHubWebhookController::class, 'handle'])
    ->middleware('validate-webhook:github');
```

**Create the controller** at `app/Http/Controllers/GitHubWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Webhook is already validated by the middleware
        $payload = json_decode($request->getContent(), true);
        $event = $request->header('X-GitHub-Event');

        // Route to specific event handlers
        match ($event) {
            'push' => $this->handlePush($payload),
            'pull_request' => $this->handlePullRequest($payload),
            'pull_request_review' => $this->handlePullRequestReview($payload),
            'issues' => $this->handleIssues($payload),
            'issue_comment' => $this->handleIssueComment($payload),
            'release' => $this->handleRelease($payload),
            'workflow_run' => $this->handleWorkflowRun($payload),
            'deployment' => $this->handleDeployment($payload),
            'star' => $this->handleStar($payload),
            default => $this->handleUnknownEvent($event, $payload),
        };

        return response()->json(['status' => 'success']);
    }

    private function handlePush(array $payload): void
    {
        $repository = $payload['repository']['full_name'];
        $branch = str_replace('refs/heads/', '', $payload['ref']);
        $commits = count($payload['commits']);
        $pusher = $payload['pusher']['name'];

        Log::info('GitHub: Push event received', [
            'repository' => $repository,
            'branch' => $branch,
            'commits' => $commits,
            'pusher' => $pusher,
        ]);

        // Example: Trigger deployment for main branch
        // if ($branch === 'main') {
        //     Artisan::call('deploy:production');
        // }
    }

    private function handlePullRequest(array $payload): void
    {
        $action = $payload['action'];
        $pr = $payload['pull_request'];

        Log::info('GitHub: Pull request ' . $action, [
            'pr_number' => $pr['number'],
            'title' => $pr['title'],
            'author' => $pr['user']['login'],
            'state' => $pr['state'],
        ]);

        match ($action) {
            'opened' => $this->handlePullRequestOpened($pr),
            'closed' => $this->handlePullRequestClosed($pr),
            'reopened' => $this->handlePullRequestReopened($pr),
            'synchronize' => $this->handlePullRequestSynchronize($pr),
            default => null,
        };
    }

    private function handlePullRequestOpened(array $pr): void
    {
        // Example: Send notification to Slack
        // Notification::route('slack', config('services.slack.webhook'))
        //     ->notify(new NewPullRequestNotification($pr));
    }

    private function handlePullRequestClosed(array $pr): void
    {
        if ($pr['merged']) {
            Log::info('GitHub: Pull request merged', [
                'pr_number' => $pr['number'],
                'merged_by' => $pr['merged_by']['login'] ?? 'unknown',
            ]);
        } else {
            Log::info('GitHub: Pull request closed without merge', [
                'pr_number' => $pr['number'],
            ]);
        }
    }

    private function handlePullRequestReopened(array $pr): void
    {
        Log::info('GitHub: Pull request reopened', [
            'pr_number' => $pr['number'],
        ]);
    }

    private function handlePullRequestSynchronize(array $pr): void
    {
        Log::info('GitHub: Pull request synchronized (new commits)', [
            'pr_number' => $pr['number'],
        ]);

        // Example: Trigger CI/CD pipeline
        // Artisan::call('ci:run', ['pr' => $pr['number']]);
    }

    private function handlePullRequestReview(array $payload): void
    {
        $review = $payload['review'];
        $pr = $payload['pull_request'];

        Log::info('GitHub: Pull request review submitted', [
            'pr_number' => $pr['number'],
            'reviewer' => $review['user']['login'],
            'state' => $review['state'],
        ]);
    }

    private function handleIssues(array $payload): void
    {
        $action = $payload['action'];
        $issue = $payload['issue'];

        Log::info('GitHub: Issue ' . $action, [
            'issue_number' => $issue['number'],
            'title' => $issue['title'],
            'author' => $issue['user']['login'],
        ]);
    }

    private function handleIssueComment(array $payload): void
    {
        $action = $payload['action'];
        $comment = $payload['comment'];
        $issue = $payload['issue'];

        Log::info('GitHub: Issue comment ' . $action, [
            'issue_number' => $issue['number'],
            'commenter' => $comment['user']['login'],
        ]);
    }

    private function handleRelease(array $payload): void
    {
        $action = $payload['action'];
        $release = $payload['release'];

        Log::info('GitHub: Release ' . $action, [
            'tag' => $release['tag_name'],
            'name' => $release['name'],
            'author' => $release['author']['login'],
        ]);

        if ($action === 'published') {
            // Example: Deploy to production
            // Artisan::call('deploy:production', ['version' => $release['tag_name']]);
        }
    }

    private function handleWorkflowRun(array $payload): void
    {
        $workflow = $payload['workflow_run'];

        Log::info('GitHub: Workflow run ' . $workflow['conclusion'], [
            'workflow' => $workflow['name'],
            'status' => $workflow['status'],
            'conclusion' => $workflow['conclusion'],
        ]);
    }

    private function handleDeployment(array $payload): void
    {
        $deployment = $payload['deployment'];

        Log::info('GitHub: Deployment event', [
            'environment' => $deployment['environment'],
            'ref' => $deployment['ref'],
        ]);
    }

    private function handleStar(array $payload): void
    {
        $action = $payload['action'];
        $repository = $payload['repository']['full_name'];
        $stargazer = $payload['sender']['login'];

        Log::info('GitHub: Repository ' . ($action === 'created' ? 'starred' : 'unstarred'), [
            'repository' => $repository,
            'stargazer' => $stargazer,
            'stars' => $payload['repository']['stargazers_count'],
        ]);
    }

    private function handleUnknownEvent(string $event, array $payload): void
    {
        Log::warning('GitHub: Unknown event type received', [
            'event_type' => $event,
        ]);
    }
}
```

#### 3. Webhook Flow Diagram

```
┌─────────────────┐         ┌──────────────────────┐         ┌─────────────────────┐
│                 │         │                      │         │                     │
│  GitHub Server  │────────▶│  LaraWebhook         │────────▶│  Your Application   │
│                 │  POST   │  - Validates         │  Valid  │  - Process event    │
│  (Webhook)      │         │    X-Hub-Signature   │         │  - Trigger CI/CD    │
│                 │         │  - Logs event        │         │  - Send messages    │
└─────────────────┘         │  - Returns response  │         │                     │
                            └──────────────────────┘         └─────────────────────┘
                                      │
                                      │ Invalid signature
                                      ▼
                            ┌──────────────────────┐
                            │  Returns 403         │
                            │  Forbidden           │
                            └──────────────────────┘
```

#### 4. Example Log Entry

Successful webhook processing creates a log entry:

```json
{
  "id": 2,
  "service": "github",
  "event": "push",
  "status": "success",
  "payload": {
    "ref": "refs/heads/main",
    "repository": {
      "full_name": "username/repository",
      "html_url": "https://github.com/username/repository"
    },
    "pusher": {
      "name": "username"
    },
    "commits": [
      {
        "id": "abc123def456",
        "message": "feat: add new feature",
        "author": {
          "name": "John Doe",
          "email": "john@example.com"
        }
      }
    ]
  },
  "attempt": 0,
  "error_message": null,
  "created_at": "2024-01-15 14:25:00"
}
```

#### 5. Configure Webhook in GitHub

1. Go to your repository **Settings** → **Webhooks** → **Add webhook**
2. **Payload URL**: `https://your-domain.com/github-webhook`
3. **Content type**: `application/json`
4. **Secret**: Enter a strong secret and add it to your `.env` file
5. **Events**: Select individual events or "Send me everything"
6. **Active**: Check this box
7. Click **Add webhook**

#### 6. Testing & Debugging

**View webhook logs:**
```bash
php artisan tinker
>>> \Proxynth\LaraWebhook\Models\WebhookLog::where('service', 'github')->latest()->first();
```

**Test webhook delivery:**
1. Go to your repository **Settings** → **Webhooks**
2. Click on your webhook
3. Scroll to **Recent Deliveries**
4. Click **Redeliver** on any delivery to resend it

---

### 💬 Slack Integration

#### 1. Configuration

Add your Slack signing secret to `.env`:

```env
SLACK_WEBHOOK_SECRET=your_slack_signing_secret_here
```

Get your signing secret from your Slack app settings:
1. Go to [Slack API](https://api.slack.com/apps)
2. Select your app
3. Go to **Basic Information** → **App Credentials**
4. Copy the **Signing Secret**

#### 2. Create Route and Controller

**Define the webhook route** in `routes/web.php`:

```php
use App\Http\Controllers\SlackWebhookController;

Route::post('/slack-webhook', [SlackWebhookController::class, 'handle'])
    ->middleware('validate-webhook:slack');
```

**Create the controller** at `app/Http/Controllers/SlackWebhookController.php`:

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
            default => $this->handleUnknownEvent($eventType, $payload),
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

        // Example: Reply to the mention
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

    private function handleUnknownEvent(string $eventType, array $payload): void
    {
        Log::warning('Slack: Unknown event type', ['event_type' => $eventType]);
    }
}
```

#### 3. Signature Validation Flow

```
┌─────────────────┐         ┌──────────────────────┐         ┌─────────────────────┐
│                 │         │                      │         │                     │
│  Slack Server   │────────▶│  LaraWebhook         │────────▶│  Your Application   │
│                 │  POST   │  - Validates         │  Valid  │  - Process event    │
│  (Event/Action) │         │    X-Slack-Signature │         │  - Reply to users   │
│                 │         │  - Checks timestamp  │         │  - Update state     │
└─────────────────┘         │  - Logs event        │         │                     │
                            └──────────────────────┘         └─────────────────────┘
```

#### 4. Configure Webhook in Slack

1. Go to [Slack API](https://api.slack.com/apps) and select your app
2. Navigate to **Event Subscriptions** (for events) or **Interactivity & Shortcuts** (for interactions)
3. Enable the feature and enter your URL: `https://your-domain.com/slack-webhook`
4. For events, subscribe to the events you want (e.g., `app_mention`, `message.channels`)
5. Save changes and reinstall the app if prompted

---

### 🛒 Shopify Integration

#### 1. Configuration

Add your Shopify webhook secret to `.env`:

```env
SHOPIFY_WEBHOOK_SECRET=your_shopify_webhook_secret_here
```

#### 2. Create Route and Controller

**Define the webhook route** in `routes/web.php`:

```php
use App\Http\Controllers\ShopifyWebhookController;

Route::post('/shopify-webhook', [ShopifyWebhookController::class, 'handle'])
    ->middleware('validate-webhook:shopify');
```

**Create the controller** at `app/Http/Controllers/ShopifyWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $topic = $request->header('X-Shopify-Topic');
        $shopDomain = $request->header('X-Shopify-Shop-Domain');

        Log::info('Shopify webhook received', [
            'topic' => $topic,
            'shop' => $shopDomain,
        ]);

        match ($topic) {
            'orders/create' => $this->handleOrderCreate($payload),
            'orders/updated' => $this->handleOrderUpdated($payload),
            'orders/cancelled' => $this->handleOrderCancelled($payload),
            'orders/fulfilled' => $this->handleOrderFulfilled($payload),
            'products/create' => $this->handleProductCreate($payload),
            'products/update' => $this->handleProductUpdate($payload),
            'products/delete' => $this->handleProductDelete($payload),
            'customers/create' => $this->handleCustomerCreate($payload),
            'refunds/create' => $this->handleRefundCreate($payload),
            default => $this->handleUnknownTopic($topic, $payload),
        };

        return response()->json(['status' => 'success']);
    }

    private function handleOrderCreate(array $payload): void
    {
        Log::info('Shopify: Order created', [
            'order_id' => $payload['id'],
            'order_number' => $payload['order_number'],
            'total_price' => $payload['total_price'],
            'customer_email' => $payload['email'],
        ]);

        // Example: Sync order to your database
        // Order::create([
        //     'shopify_id' => $payload['id'],
        //     'number' => $payload['order_number'],
        //     'total' => $payload['total_price'],
        //     'currency' => $payload['currency'],
        //     'status' => $payload['financial_status'],
        // ]);
    }

    private function handleOrderUpdated(array $payload): void
    {
        Log::info('Shopify: Order updated', [
            'order_id' => $payload['id'],
            'financial_status' => $payload['financial_status'],
        ]);
    }

    private function handleOrderCancelled(array $payload): void
    {
        Log::info('Shopify: Order cancelled', [
            'order_id' => $payload['id'],
            'cancel_reason' => $payload['cancel_reason'] ?? 'unknown',
        ]);
    }

    private function handleOrderFulfilled(array $payload): void
    {
        Log::info('Shopify: Order fulfilled', [
            'order_id' => $payload['id'],
        ]);
    }

    private function handleProductCreate(array $payload): void
    {
        Log::info('Shopify: Product created', [
            'product_id' => $payload['id'],
            'title' => $payload['title'],
        ]);
    }

    private function handleProductUpdate(array $payload): void
    {
        Log::info('Shopify: Product updated', [
            'product_id' => $payload['id'],
        ]);
    }

    private function handleProductDelete(array $payload): void
    {
        Log::info('Shopify: Product deleted', [
            'product_id' => $payload['id'],
        ]);
    }

    private function handleCustomerCreate(array $payload): void
    {
        Log::info('Shopify: Customer created', [
            'customer_id' => $payload['id'],
            'email' => $payload['email'],
        ]);
    }

    private function handleRefundCreate(array $payload): void
    {
        Log::info('Shopify: Refund created', [
            'refund_id' => $payload['id'],
            'order_id' => $payload['order_id'],
        ]);
    }

    private function handleUnknownTopic(?string $topic, array $payload): void
    {
        Log::warning('Shopify: Unknown topic', ['topic' => $topic]);
    }
}
```

#### 3. Signature Validation Flow

```
┌─────────────────┐         ┌──────────────────────┐         ┌─────────────────────┐
│                 │         │                      │         │                     │
│  Shopify Server │────────▶│  LaraWebhook         │────────▶│  Your Application   │
│                 │  POST   │  - Validates HMAC    │  Valid  │  - Sync orders      │
│  (Webhook)      │         │    X-Shopify-Hmac    │         │  - Update inventory │
│                 │         │  - Logs event        │         │  - Process refunds  │
└─────────────────┘         │  - Returns 200       │         │                     │
                            └──────────────────────┘         └─────────────────────┘
```

#### 4. Configure Webhook in Shopify

**Via Shopify Admin:**
1. Go to **Settings** → **Notifications** → **Webhooks**
2. Click **Create webhook**
3. Select the event (e.g., `Order creation`)
4. Format: **JSON**
5. URL: `https://your-domain.com/shopify-webhook`
6. API version: Select the latest stable version
7. Click **Save**
8. Copy the webhook signing secret and add to your `.env`

**Via Shopify API:**
```bash
curl -X POST "https://your-shop.myshopify.com/admin/api/2024-01/webhooks.json" \
  -H "X-Shopify-Access-Token: YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "webhook": {
      "topic": "orders/create",
      "address": "https://your-domain.com/shopify-webhook",
      "format": "json"
    }
  }'
```

#### 5. Testing with Shopify CLI

```bash
# Install Shopify CLI
npm install -g @shopify/cli @shopify/theme

# Test webhook delivery
shopify webhook trigger --topic orders/create \
  --api-version 2024-01 \
  --delivery-method http \
  --address https://your-domain.com/shopify-webhook
```

---

### 🔒 Best Practices

#### Security

**✅ Always use HTTPS in production**
```php
// Force HTTPS for webhook routes in production
if (app()->environment('production')) {
    URL::forceScheme('https');
}
```

**✅ Validate webhook signatures**
```php
// The validate-webhook middleware does this automatically
Route::post('/webhook', [Controller::class, 'handle'])
    ->middleware('validate-webhook:stripe');
```

**✅ Keep secrets in environment variables**
```env
# .env file (NEVER commit this file)
STRIPE_WEBHOOK_SECRET=whsec_your_secret_here
GITHUB_WEBHOOK_SECRET=your_github_secret_here
```

**✅ Rotate secrets regularly**
- Update secrets in your webhook provider dashboard
- Update `.env` file
- Deploy the change
- Delete old webhook endpoint after verifying the new one works

**✅ Limit webhook IP addresses (optional)**
```php
// Only accept webhooks from Stripe IPs
Route::post('/stripe-webhook', [StripeWebhookController::class, 'handle'])
    ->middleware(['validate-webhook:stripe', 'throttle:60,1']);
```

#### Error Handling

**✅ Log all webhook events**
```php
// LaraWebhook automatically logs all webhooks to the database
// View them in the dashboard: /larawebhook/dashboard
```

**✅ Handle failures gracefully**
```php
private function handlePaymentFailed(array $payload): void
{
    try {
        // Process the event
        $this->processPayment($payload);
    } catch (\Exception $e) {
        // Log the error
        Log::error('Failed to process payment webhook', [
            'error' => $e->getMessage(),
            'payload' => $payload,
        ]);

        // Notify administrators
        // Notification::route('slack', config('services.slack.webhook'))
        //     ->notify(new WebhookProcessingFailed($e, $payload));
    }
}
```

**✅ Use try-catch for external calls**
```php
private function handlePush(array $payload): void
{
    try {
        // Call external service
        Http::timeout(5)->post('https://external-api.com/deploy', [
            'repository' => $payload['repository']['name'],
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to trigger deployment', [
            'error' => $e->getMessage(),
        ]);
        // Don't throw - webhook should still return 200 OK
    }
}
```

#### Performance

**✅ Process webhooks asynchronously with queues**
```php
public function handle(Request $request): JsonResponse
{
    $payload = json_decode($request->getContent(), true);
    $event = $payload['type'];

    // Dispatch to queue for async processing
    ProcessStripeWebhook::dispatch($event, $payload);

    // Return 200 immediately
    return response()->json(['status' => 'success']);
}
```

**✅ Set reasonable timeouts**
```php
// Don't let webhook processing block the response
set_time_limit(30); // 30 seconds max
```

#### Monitoring

**✅ Monitor webhook failures**
```bash
# Check for recent failures
php artisan tinker
>>> \Proxynth\LaraWebhook\Models\WebhookLog::where('status', 'failed')
        ->where('created_at', '>', now()->subHour())
        ->count();
```

**✅ Enable automatic failure notifications**
```env
# LaraWebhook has built-in notifications for repeated failures
WEBHOOK_NOTIFICATIONS_ENABLED=true
WEBHOOK_NOTIFICATION_CHANNELS=mail,slack
WEBHOOK_EMAIL_RECIPIENTS=admin@example.com
WEBHOOK_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
WEBHOOK_FAILURE_THRESHOLD=3
```

See the [Failure Notifications](#-failure-notifications) section for complete setup.

**✅ Use the dashboard for monitoring**
- Access at `/larawebhook/dashboard`
- Filter by service, status, date
- Replay failed webhooks
- View detailed payloads and error messages

---

## 💻 Code Examples

Ready-to-use code examples for common webhook integrations. Copy, paste, and customize!

### 📁 Examples Directory

The [`examples/`](examples/) directory contains fully functional controller examples:

1. **[StripeWebhookController.php](examples/StripeWebhookController.php)**
    - Complete Stripe integration with payment intents, charges, subscriptions, and invoices
    - Error handling and automatic logging
    - Production-ready code with best practices

2. **[GitHubWebhookController.php](examples/GitHubWebhookController.php)**
    - Full GitHub webhook handling (push, PR, issues, releases, workflows)
    - Auto-deployment on release
    - Automatic retry on failure

3. **[CustomServiceExample.php](examples/CustomServiceExample.php)**
    - Step-by-step guide for adding custom services (Shopify example)
    - Custom validator creation
    - Middleware and controller setup

### 🚀 Quick Start with Examples

**Option 1: Copy the Full Controller**
```bash
# Copy the example you need
cp vendor/proxynth/larawebhook/examples/StripeWebhookController.php \
   app/Http/Controllers/StripeWebhookController.php
```

**Option 2: Use as Reference**

Open the examples and copy specific methods you need:
```php
// From examples/StripeWebhookController.php
private function handlePaymentIntentSucceeded(array $payload): void
{
    $paymentIntent = $payload['data']['object'];

    // Your custom logic here
    $order = Order::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
    $order->update(['status' => 'paid']);
}
```

### 📖 Example Usage Patterns

**Pattern 1: Simple Stripe Integration**
```php
// routes/web.php
Route::post('/stripe-webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('validate-webhook:stripe');

// .env
STRIPE_WEBHOOK_SECRET=whsec_your_secret_here
```

**Pattern 2: GitHub Auto-Deploy**
```php
// From GitHubWebhookController.php
private function handlePush(array $payload): void
{
    $branch = str_replace('refs/heads/', '', $payload['ref']);

    if ($branch === 'main') {
        Artisan::call('deploy:production');
    }
}
```

**Pattern 3: Custom Service (Shopify)**
```php
// 1. Create a signature validator implementing SignatureValidatorInterface
class ShopifySignatureValidator implements SignatureValidatorInterface
{
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        $calculated = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        if (! hash_equals($calculated, $signature)) {
            throw new InvalidSignatureException('Invalid Shopify signature.');
        }
        return true;
    }

    public function serviceName(): string
    {
        return 'shopify';
    }
}

// 2. Create a payload parser implementing PayloadParserInterface
class ShopifyPayloadParser implements PayloadParserInterface
{
    public function extractEventType(array $data): string
    {
        return $data['topic'] ?? 'unknown';
    }

    public function extractMetadata(array $data): array
    {
        return ['shop_domain' => $data['shop_domain'] ?? null];
    }

    public function serviceName(): string
    {
        return 'shopify';
    }
}

// 3. Register in WebhookService enum (see Extensible Architecture section)
```

### 🔗 Full Documentation

For detailed usage instructions, testing strategies, and best practices, see:
- **[Examples README](examples/README.md)** - Complete guide with patterns and tips
- **[Integration Examples](#-service-integration-examples)** - Stripe and GitHub integration guides below

---

## 🔧 Configuration

Modify `config/larawebhook.php` to:
* Add services (Stripe, GitHub, etc.)
* Configure validation tolerance
* Enable retry management
* Set up failure notifications
* Customize the dashboard

Example:
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

'retries' => [
    'enabled' => true,
    'max_attempts' => 3,
    'delays' => [1, 5, 10], // seconds
],

'notifications' => [
    'enabled' => env('WEBHOOK_NOTIFICATIONS_ENABLED', false),
    'channels' => ['mail', 'slack'],
    'failure_threshold' => 3,
],

'dashboard' => [
    'enabled' => true,
    'path' => '/larawebhook/dashboard',
],
```

---

## 📊 Logging

Webhooks are logged in the `webhook_logs` table with:
* service (e.g., stripe, github)
* event (e.g., payment_intent.succeeded)
* status (success/failed)
* payload (webhook content)
* created_at

To view logs:
```bash
php artisan tinker
>>> \Proxynth\LaraWebhook\Models\WebhookLog::latest()->get();
```

---

## 📊 Dashboard & API

LaraWebhook provides a modern dashboard built with **Alpine.js** and **Tailwind CSS** to visualize and manage webhook logs.

### Access the Dashboard

The dashboard is automatically available at:
```
http://your-app.test/larawebhook/dashboard
```

**Features:**
- 📋 Paginated webhook logs table
- 🔍 Filter by service, status, and date
- 👁️ View detailed payload and error messages
- 🔄 Replay failed webhooks
- 📱 Fully responsive design

### API Endpoints

The package also provides REST API endpoints for programmatic access:

#### Get Webhook Logs
```http
GET /api/larawebhook/logs
```

**Query Parameters:**
- `service` - Filter by service (stripe, github, etc.)
- `status` - Filter by status (success, failed)
- `date` - Filter by date (YYYY-MM-DD)
- `per_page` - Results per page (default: 10)
- `page` - Page number

**Example:**
```bash
curl "https://your-app.test/api/larawebhook/logs?service=stripe&status=failed&per_page=25"
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "service": "stripe",
      "event": "payment_intent.succeeded",
      "status": "success",
      "payload": {...},
      "attempt": 0,
      "created_at": "01/01/2024 10:30:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 50
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

#### Replay a Webhook
```http
POST /api/larawebhook/logs/{id}/replay
```

**Example:**
```bash
curl -X POST "https://your-app.test/api/larawebhook/logs/123/replay" \
  -H "Content-Type: application/json"
```

**Response:**
```json
{
  "success": true,
  "message": "Webhook replayed successfully!",
  "log": {
    "id": 124,
    "service": "stripe",
    "event": "payment_intent.succeeded",
    "status": "success",
    "attempt": 1
  }
}
```

### Dashboard Configuration

Customize the dashboard in `config/larawebhook.php`:

```php
'dashboard' => [
    'enabled' => env('LARAWEBHOOK_DASHBOARD_ENABLED', true),
    'path' => env('LARAWEBHOOK_DASHBOARD_PATH', '/larawebhook/dashboard'),
    'middleware' => env('LARAWEBHOOK_DASHBOARD_MIDDLEWARE', 'web'),
],
```

**Disable the dashboard:**
```env
LARAWEBHOOK_DASHBOARD_ENABLED=false
```

**Change the dashboard path:**
```env
LARAWEBHOOK_DASHBOARD_PATH=/admin/webhooks
```

**Add authentication middleware:**
```env
LARAWEBHOOK_DASHBOARD_MIDDLEWARE=web,auth
```

### Dashboard Screenshots

**Main Dashboard**
![Dashboard Overview](docs/screenshots/dashboard-overview.png)

**Filtered View**
![Filtered Dashboard](docs/screenshots/dashboard-filtered.png)

**Payload Details**
![Payload Modal](docs/screenshots/dashboard-payload-modal.png)

**Success vs Failed Logs**
![Log Comparison](docs/screenshots/log-success.png)
![Failed Log](docs/screenshots/log-failed.png)

---

## 🔔 Failure Notifications

LaraWebhook can automatically notify you when webhooks fail repeatedly. Get alerted via **Email** and **Slack** when a service experiences multiple consecutive failures.

### Why Notifications?

- **Detect outages early**: Know immediately when a webhook provider has issues
- **Reduce downtime**: React quickly to integration problems
- **Team collaboration**: Send alerts to Slack channels for instant visibility

### Configuration

Enable notifications in `config/larawebhook.php`:

```php
'notifications' => [
    // Enable/disable failure notifications
    'enabled' => env('WEBHOOK_NOTIFICATIONS_ENABLED', true),

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
```

### Environment Variables

Add these to your `.env` file:

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

# Time window for counting failures in minutes (default: 30)
WEBHOOK_FAILURE_WINDOW=30

# Cooldown between notifications in minutes (default: 30)
WEBHOOK_NOTIFICATION_COOLDOWN=30
```

### How It Works

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

1. **Failure Detection**: Counts consecutive failures for each service/event combination
2. **Threshold Check**: Only triggers notification after N failures (configurable)
3. **Time Window**: Only counts failures within the last X minutes
4. **Cooldown**: Prevents notification spam by waiting between alerts

### Slack Setup

1. Go to [Slack API](https://api.slack.com/apps)
2. Click **Create New App** → **From scratch**
3. Give your app a name and select your workspace
4. Go to **Incoming Webhooks** and toggle it **On**
5. Click **Add New Webhook to Workspace**
6. Select the channel for notifications (e.g., `#alerts` or `#monitoring`)
7. Copy the webhook URL and add it to your `.env` file

**Webhook URL format:** `https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXX`

### Email Notifications

Email notifications use Laravel's built-in mail system. Make sure your mail configuration is set up in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="LaraWebhook"
```

### Notification Content

**Email Notification:**
- Subject: `Webhook Failure Alert: {service}`
- Service and event name
- Number of consecutive failures
- Last attempt timestamp
- Error message (if available)
- Link to dashboard

**Slack Notification:**
- Red alert color (danger level)
- Service and event details
- Failure count
- Error message
- Direct link to dashboard

### Events

LaraWebhook dispatches an event when a notification is sent, allowing you to add custom logic:

```php
use Proxynth\Larawebhook\Events\WebhookNotificationSent;

// In your EventServiceProvider
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

        // Example: Log to external monitoring service
        Http::post('https://monitoring.example.com/webhook-failure', [
            'service' => $event->log->service,
            'event' => $event->log->event,
            'failures' => $event->failureCount,
        ]);
    }
}
```

### Preventing Notification Spam

LaraWebhook includes built-in spam prevention:

1. **Failure Threshold**: Only notifies after N consecutive failures (default: 3)
2. **Time Window**: Only counts failures within the last X minutes (default: 30)
3. **Cooldown Period**: Won't send another notification for the same service/event within X minutes (default: 30)

Example scenario:
- Stripe `payment.failed` fails 3 times in 10 minutes → **Notification sent**
- 5 more failures in the next 20 minutes → **No notification** (cooldown active)
- After 30 minutes, 3 more failures → **Notification sent again**

### Disabling Notifications

To completely disable notifications:

```env
WEBHOOK_NOTIFICATIONS_ENABLED=false
```

Or to disable only for certain environments, use Laravel's configuration:

```php
// config/larawebhook.php
'notifications' => [
    'enabled' => env('WEBHOOK_NOTIFICATIONS_ENABLED', app()->environment('production')),
    // ... other settings
],
```

---

## 🧪 Tests

Run tests with:
```bash
composer test
```

*(Tests cover validation, retries, and logging.)*

---

## 🚀 Release Process

This project uses [Release Please](https://github.com/googleapis/release-please) for automated releases and changelog management.

### How it works

1. **Commit with Conventional Commits format:**
   ```bash
   git commit -m "feat: add new webhook validation"
   git commit -m "fix: resolve signature verification bug"
   git commit -m "docs: update installation instructions"
   ```

2. **Release Please creates a PR automatically** when changes are pushed to `master`:
    - Generates/updates `CHANGELOG.md` based on commits
    - Bumps version in `.release-please-manifest.json`
    - Creates a release PR titled "chore(master): release X.Y.Z"

3. **Review and merge the release PR:**
    - Review the generated changelog
    - Merge the PR to trigger the release

4. **Automatic actions on merge:**
    - Creates a GitHub Release with tag `vX.Y.Z`
    - Runs tests and static analysis
    - Packagist syncs automatically (no manual webhook needed)

### Conventional Commits format

- `feat:` → New feature (bumps minor version)
- `fix:` → Bug fix (bumps patch version)
- `docs:` → Documentation changes
- `style:` → Code style changes (formatting, etc.)
- `refactor:` → Code refactoring
- `perf:` → Performance improvements
- `test:` → Adding/updating tests
- `chore:` → Maintenance tasks
- `ci:` → CI/CD changes

**Breaking changes:** Add `!` after type or add `BREAKING CHANGE:` in commit body to bump major version.

Example:
```bash
git commit -m "feat!: change webhook validation API"
```

---

## 🤝 Contributing

1. Fork the repository
2. Create a branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -am 'Add my feature'`)
4. Push the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

*(See CONTRIBUTING.md for more details.)*

---

## 📄 License

This project is licensed under the MIT License. See LICENSE for more information.
