# Stripe Integration

## Configuration

Add your Stripe webhook secret to `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_your_stripe_webhook_secret_here
```

Get your secret from [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks).

## Route Setup

```php
// routes/web.php
use App\Http\Controllers\StripeWebhookController;

Route::post('/stripe-webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('validate-webhook:stripe');
```

## Controller Example

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
        $payload = json_decode($request->getContent(), true);
        $event = $payload['type'] ?? 'unknown';

        match ($event) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($payload),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($payload),
            'customer.subscription.created' => $this->handleSubscriptionCreated($payload),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($payload),
            'invoice.paid' => $this->handleInvoicePaid($payload),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($payload),
            default => $this->handleUnknown($event),
        };

        return response()->json(['status' => 'success']);
    }

    private function handlePaymentSucceeded(array $payload): void
    {
        $paymentIntent = $payload['data']['object'];

        Log::info('Stripe: Payment succeeded', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount'],
            'currency' => $paymentIntent['currency'],
        ]);

        // Update order status
        // Order::where('stripe_payment_intent_id', $paymentIntent['id'])
        //     ->update(['status' => 'paid']);
    }

    private function handlePaymentFailed(array $payload): void
    {
        $paymentIntent = $payload['data']['object'];

        Log::error('Stripe: Payment failed', [
            'payment_intent_id' => $paymentIntent['id'],
            'error' => $paymentIntent['last_payment_error'],
        ]);
    }

    private function handleSubscriptionCreated(array $payload): void
    {
        $subscription = $payload['data']['object'];

        Log::info('Stripe: Subscription created', [
            'subscription_id' => $subscription['id'],
            'customer' => $subscription['customer'],
        ]);

        // Grant premium access
        // User::where('stripe_customer_id', $subscription['customer'])
        //     ->update(['subscription_status' => 'active']);
    }

    private function handleSubscriptionDeleted(array $payload): void
    {
        $subscription = $payload['data']['object'];

        Log::info('Stripe: Subscription deleted', [
            'subscription_id' => $subscription['id'],
        ]);

        // Revoke premium access
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

    private function handleUnknown(string $event): void
    {
        Log::warning('Stripe: Unknown event', ['event' => $event]);
    }
}
```

## Signature Validation

Stripe uses a specific signature format:

```
t=timestamp,v1=signature
```

LaraWebhook automatically:
1. Parses the `Stripe-Signature` header
2. Validates the timestamp against tolerance (default: 300s)
3. Computes and compares the HMAC-SHA256 signature

## Testing with Stripe CLI

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login to your Stripe account
stripe login

# Forward webhooks to your local environment
stripe listen --forward-to http://localhost:8000/stripe-webhook

# Trigger a test webhook
stripe trigger payment_intent.succeeded
```

## Configure in Stripe Dashboard

1. Go to [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks)
2. Click **Add endpoint**
3. Enter URL: `https://your-domain.com/stripe-webhook`
4. Select events to listen for
5. Copy the **Signing secret** to your `.env`

## Common Events

| Event | Description |
|-------|-------------|
| `payment_intent.succeeded` | Payment completed |
| `payment_intent.payment_failed` | Payment failed |
| `charge.succeeded` | Charge completed |
| `charge.refunded` | Charge refunded |
| `customer.subscription.created` | New subscription |
| `customer.subscription.updated` | Subscription changed |
| `customer.subscription.deleted` | Subscription cancelled |
| `invoice.paid` | Invoice paid |
| `invoice.payment_failed` | Invoice payment failed |
