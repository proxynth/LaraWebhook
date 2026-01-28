<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Webhook Controller Example
 *
 * This example shows how to handle Stripe webhooks with LaraWebhook.
 *
 * Setup:
 * 1. Add to your .env:
 *    STRIPE_WEBHOOK_SECRET=whsec_your_stripe_webhook_secret
 *
 * 2. Add to config/larawebhook.php:
 *    'services' => [
 *        'stripe' => [
 *            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
 *            'tolerance' => 300,
 *        ],
 *    ]
 *
 * 3. Add to routes/web.php:
 *    Route::post('/stripe-webhook', [StripeWebhookController::class, 'handle'])
 *        ->middleware('validate-webhook:stripe');
 *
 * 4. Configure in Stripe Dashboard:
 *    - Go to https://dashboard.stripe.com/webhooks
 *    - Add endpoint: https://your-domain.com/stripe-webhook
 *    - Copy the signing secret to your .env
 */
class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook.
     *
     * The webhook is already validated by the 'validate-webhook:stripe' middleware,
     * so you can safely process the event here.
     */
    public function handle(Request $request): JsonResponse
    {
        // Get the webhook payload
        $payload = json_decode($request->getContent(), true);
        $eventType = $payload['type'] ?? 'unknown';

        Log::info('Stripe webhook received', [
            'event_type' => $eventType,
            'event_id' => $payload['id'] ?? null,
        ]);

        // Route to specific event handlers
        try {
            match ($eventType) {
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($payload),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($payload),
                'charge.succeeded' => $this->handleChargeSucceeded($payload),
                'charge.failed' => $this->handleChargeFailed($payload),
                'customer.subscription.created' => $this->handleSubscriptionCreated($payload),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($payload),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($payload),
                'invoice.paid' => $this->handleInvoicePaid($payload),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($payload),
                default => $this->handleUnknownEvent($eventType, $payload),
            };
        } catch (\Exception $e) {
            Log::error('Error processing Stripe webhook', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            // Still return 200 to acknowledge receipt
            // Stripe will retry if we return non-2xx
            return response()->json([
                'status' => 'error',
                'message' => 'Event processing failed but webhook acknowledged',
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle successful payment intent.
     */
    private function handlePaymentIntentSucceeded(array $payload): void
    {
        $paymentIntent = $payload['data']['object'];

        Log::info('Payment intent succeeded', [
            'payment_intent_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount'],
            'currency' => $paymentIntent['currency'],
            'customer' => $paymentIntent['customer'] ?? null,
        ]);

        // Example: Update order status in database
        // $order = Order::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
        // if ($order) {
        //     $order->update(['status' => 'paid', 'paid_at' => now()]);
        //
        //     // Send confirmation email
        //     Mail::to($order->customer->email)->send(new OrderPaidMail($order));
        // }
    }

    /**
     * Handle failed payment intent.
     */
    private function handlePaymentIntentFailed(array $payload): void
    {
        $paymentIntent = $payload['data']['object'];
        $error = $paymentIntent['last_payment_error'] ?? [];

        Log::error('Payment intent failed', [
            'payment_intent_id' => $paymentIntent['id'],
            'error_code' => $error['code'] ?? 'unknown',
            'error_message' => $error['message'] ?? 'No error message',
        ]);

        // Example: Notify customer and update order
        // $order = Order::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
        // if ($order) {
        //     $order->update(['status' => 'payment_failed']);
        //
        //     // Send failure notification
        //     Mail::to($order->customer->email)->send(new PaymentFailedMail($order, $error));
        // }
    }

    /**
     * Handle successful charge.
     */
    private function handleChargeSucceeded(array $payload): void
    {
        $charge = $payload['data']['object'];

        Log::info('Charge succeeded', [
            'charge_id' => $charge['id'],
            'amount' => $charge['amount'],
            'receipt_url' => $charge['receipt_url'] ?? null,
        ]);

        // Example: Store receipt URL
        // $payment = Payment::where('stripe_charge_id', $charge['id'])->first();
        // if ($payment) {
        //     $payment->update(['receipt_url' => $charge['receipt_url']]);
        // }
    }

    /**
     * Handle failed charge.
     */
    private function handleChargeFailed(array $payload): void
    {
        $charge = $payload['data']['object'];

        Log::error('Charge failed', [
            'charge_id' => $charge['id'],
            'failure_code' => $charge['failure_code'] ?? 'unknown',
            'failure_message' => $charge['failure_message'] ?? 'No failure message',
        ]);
    }

    /**
     * Handle subscription creation.
     */
    private function handleSubscriptionCreated(array $payload): void
    {
        $subscription = $payload['data']['object'];

        Log::info('Subscription created', [
            'subscription_id' => $subscription['id'],
            'customer' => $subscription['customer'],
            'status' => $subscription['status'],
        ]);

        // Example: Grant access to premium features
        // $user = User::where('stripe_customer_id', $subscription['customer'])->first();
        // if ($user) {
        //     $user->update([
        //         'subscription_status' => $subscription['status'],
        //         'subscription_id' => $subscription['id'],
        //         'subscribed_at' => now(),
        //     ]);
        //
        //     // Send welcome email with premium features info
        //     Mail::to($user->email)->send(new SubscriptionActivatedMail($user));
        // }
    }

    /**
     * Handle subscription update.
     */
    private function handleSubscriptionUpdated(array $payload): void
    {
        $subscription = $payload['data']['object'];

        Log::info('Subscription updated', [
            'subscription_id' => $subscription['id'],
            'status' => $subscription['status'],
        ]);

        // Example: Update user subscription status
        // $user = User::where('stripe_customer_id', $subscription['customer'])->first();
        // if ($user) {
        //     $user->update(['subscription_status' => $subscription['status']]);
        // }
    }

    /**
     * Handle subscription deletion/cancellation.
     */
    private function handleSubscriptionDeleted(array $payload): void
    {
        $subscription = $payload['data']['object'];

        Log::info('Subscription deleted', [
            'subscription_id' => $subscription['id'],
        ]);

        // Example: Revoke access to premium features
        // $user = User::where('stripe_customer_id', $subscription['customer'])->first();
        // if ($user) {
        //     $user->update([
        //         'subscription_status' => 'canceled',
        //         'subscription_id' => null,
        //     ]);
        //
        //     // Send cancellation confirmation
        //     Mail::to($user->email)->send(new SubscriptionCanceledMail($user));
        // }
    }

    /**
     * Handle paid invoice.
     */
    private function handleInvoicePaid(array $payload): void
    {
        $invoice = $payload['data']['object'];

        Log::info('Invoice paid', [
            'invoice_id' => $invoice['id'],
            'amount_paid' => $invoice['amount_paid'],
        ]);

        // Example: Record payment and send receipt
        // $payment = Payment::create([
        //     'stripe_invoice_id' => $invoice['id'],
        //     'amount' => $invoice['amount_paid'],
        //     'currency' => $invoice['currency'],
        //     'paid_at' => now(),
        // ]);
        //
        // Mail::to($invoice['customer_email'])->send(new InvoicePaidMail($payment));
    }

    /**
     * Handle failed invoice payment.
     */
    private function handleInvoicePaymentFailed(array $payload): void
    {
        $invoice = $payload['data']['object'];

        Log::error('Invoice payment failed', [
            'invoice_id' => $invoice['id'],
            'attempt_count' => $invoice['attempt_count'],
        ]);

        // Example: Notify customer about payment issue
        // Mail::to($invoice['customer_email'])->send(new InvoicePaymentFailedMail($invoice));
    }

    /**
     * Handle unknown event types.
     */
    private function handleUnknownEvent(string $eventType, array $payload): void
    {
        Log::warning('Unknown Stripe event type received', [
            'event_type' => $eventType,
            'event_id' => $payload['id'] ?? null,
        ]);

        // You can add logging or monitoring here for new event types
        // that Stripe might introduce in the future
    }
}
