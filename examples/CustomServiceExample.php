<?php

/**
 * Custom Service Webhook Example (PayPal)
 *
 * This example shows how to add support for a custom webhook service
 * using LaraWebhook's extensible Strategy Pattern architecture.
 *
 * LaraWebhook natively supports: Stripe, GitHub, Slack, Shopify
 *
 * The same pattern applies to any additional service:
 * - PayPal (this example)
 * - Mailchimp
 * - SendGrid
 * - Twilio
 * - Square
 * - etc.
 *
 * LaraWebhook uses two Strategy interfaces:
 * - PayloadParserInterface: Extracts event type and metadata from payloads
 * - SignatureValidatorInterface: Validates webhook signatures
 */

namespace App\Webhook\Parsers;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;

/**
 * Step 1: Create a Payload Parser
 *
 * Implement PayloadParserInterface to handle PayPal's payload format.
 *
 * @see https://developer.paypal.com/api/rest/webhooks/
 */
class PayPalPayloadParser implements PayloadParserInterface
{
    /**
     * Extract the event type from PayPal payload.
     *
     * PayPal uses "event_type" field (e.g., PAYMENT.CAPTURE.COMPLETED)
     */
    public function extractEventType(array $data): string
    {
        return $data['event_type'] ?? 'unknown';
    }

    /**
     * Extract metadata from PayPal payload.
     */
    public function extractMetadata(array $data): array
    {
        $resource = $data['resource'] ?? [];

        return [
            'event_id' => $data['id'] ?? null,
            'event_type' => $data['event_type'] ?? null,
            'resource_type' => $data['resource_type'] ?? null,
            'resource_id' => $resource['id'] ?? null,
            'amount' => $resource['amount']['value'] ?? null,
            'currency' => $resource['amount']['currency_code'] ?? null,
            'status' => $resource['status'] ?? null,
            'create_time' => $data['create_time'] ?? null,
        ];
    }

    public function serviceName(): string
    {
        return 'paypal';
    }
}

/*
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Webhook\Validators;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;

/**
 * Step 2: Create a Signature Validator
 *
 * PayPal uses certificate-based validation or transmission signature.
 * This example shows simplified signature validation.
 *
 * @see https://developer.paypal.com/docs/api-basics/notifications/webhooks/notification-messages/
 */
class PayPalSignatureValidator implements SignatureValidatorInterface
{
    /**
     * Validate PayPal webhook signature.
     *
     * PayPal sends several headers for validation:
     * - PAYPAL-TRANSMISSION-ID
     * - PAYPAL-TRANSMISSION-TIME
     * - PAYPAL-TRANSMISSION-SIG
     * - PAYPAL-CERT-URL
     *
     * For simplicity, this example validates using webhook ID + transmission data.
     * In production, you should use PayPal's certificate-based validation.
     *
     * @throws InvalidSignatureException
     */
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        // Signature format: "transmission_id|transmission_time|webhook_id|crc32"
        $parts = explode('|', $signature);

        if (count($parts) < 4) {
            throw new InvalidSignatureException('Invalid PayPal signature format.');
        }

        [$transmissionId, $transmissionTime, $webhookId, $expectedCrc] = $parts;

        // Verify the webhook ID matches our secret (webhook ID)
        if ($webhookId !== $secret) {
            throw new InvalidSignatureException('Invalid PayPal webhook ID.');
        }

        // Verify CRC32 checksum of the payload
        $actualCrc = sprintf('%u', crc32($payload));
        if ($actualCrc !== $expectedCrc) {
            throw new InvalidSignatureException('Invalid PayPal webhook signature (CRC mismatch).');
        }

        return true;
    }

    public function serviceName(): string
    {
        return 'paypal';
    }
}

/*
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Step 3: Register in WebhookService Enum
 *
 * Add your service to src/Enums/WebhookService.php:
 *
 * enum WebhookService: string
 * {
 *     case Stripe = 'stripe';
 *     case Github = 'github';
 *     case Slack = 'slack';
 *     case Shopify = 'shopify';
 *     case PayPal = 'paypal';  // Add new case
 *
 *     public function parser(): PayloadParserInterface
 *     {
 *         return match ($this) {
 *             self::Stripe => new StripePayloadParser,
 *             self::Github => new GithubPayloadParser,
 *             self::Slack => new SlackPayloadParser,
 *             self::Shopify => new ShopifyPayloadParser,
 *             self::PayPal => new PayPalPayloadParser,  // Add mapping
 *         };
 *     }
 *
 *     public function signatureValidator(): SignatureValidatorInterface
 *     {
 *         return match ($this) {
 *             self::Stripe => new StripeSignatureValidator,
 *             self::Github => new GithubSignatureValidator,
 *             self::Slack => new SlackSignatureValidator,
 *             self::Shopify => new ShopifySignatureValidator,
 *             self::PayPal => new PayPalSignatureValidator,  // Add mapping
 *         };
 *     }
 *
 *     public function signatureHeader(): string
 *     {
 *         return match ($this) {
 *             self::Stripe => 'Stripe-Signature',
 *             self::Github => 'X-Hub-Signature-256',
 *             self::Slack => 'X-Slack-Signature',
 *             self::Shopify => 'X-Shopify-Hmac-Sha256',
 *             self::PayPal => 'PAYPAL-TRANSMISSION-SIG',  // Add header
 *         };
 *     }
 * }
 *
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Step 4: Add Configuration
 *
 * In config/larawebhook.php:
 */

/*
'services' => [
    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300,
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
    'paypal' => [
        'webhook_secret' => env('PAYPAL_WEBHOOK_ID'),  // PayPal uses webhook ID
        'tolerance' => 300,
    ],
],
*/

/*
 * In .env:
 *
 * PAYPAL_WEBHOOK_ID=your_paypal_webhook_id
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Step 5: Create the Controller
 *
 * Use the standard validate-webhook middleware - it now works with your service!
 */
class PayPalWebhookController extends Controller
{
    /**
     * Handle PayPal webhooks.
     *
     * Route: POST /paypal-webhook
     * Middleware: validate-webhook:paypal
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $eventType = $payload['event_type'] ?? 'unknown';

        Log::info('PayPal webhook received', [
            'event_type' => $eventType,
            'event_id' => $payload['id'] ?? null,
        ]);

        // Route to specific handlers based on event type
        match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->handlePaymentCaptured($payload),
            'PAYMENT.CAPTURE.DENIED' => $this->handlePaymentDenied($payload),
            'PAYMENT.CAPTURE.REFUNDED' => $this->handlePaymentRefunded($payload),
            'CHECKOUT.ORDER.APPROVED' => $this->handleOrderApproved($payload),
            'CHECKOUT.ORDER.COMPLETED' => $this->handleOrderCompleted($payload),
            'BILLING.SUBSCRIPTION.CREATED' => $this->handleSubscriptionCreated($payload),
            'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleSubscriptionActivated($payload),
            'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleSubscriptionCancelled($payload),
            'INVOICING.INVOICE.PAID' => $this->handleInvoicePaid($payload),
            default => $this->handleUnknownEvent($eventType, $payload),
        };

        return response()->json(['status' => 'success']);
    }

    private function handlePaymentCaptured(array $payload): void
    {
        $resource = $payload['resource'] ?? [];

        Log::info('PayPal payment captured', [
            'capture_id' => $resource['id'] ?? null,
            'amount' => $resource['amount']['value'] ?? null,
            'currency' => $resource['amount']['currency_code'] ?? null,
        ]);

        // Example: Update order status
        // Order::where('paypal_order_id', $resource['supplementary_data']['related_ids']['order_id'] ?? null)
        //     ->update(['status' => 'paid']);
    }

    private function handlePaymentDenied(array $payload): void
    {
        Log::warning('PayPal payment denied', [
            'resource_id' => $payload['resource']['id'] ?? null,
        ]);
    }

    private function handlePaymentRefunded(array $payload): void
    {
        Log::info('PayPal payment refunded', [
            'resource_id' => $payload['resource']['id'] ?? null,
        ]);
    }

    private function handleOrderApproved(array $payload): void
    {
        Log::info('PayPal order approved', [
            'order_id' => $payload['resource']['id'] ?? null,
        ]);
    }

    private function handleOrderCompleted(array $payload): void
    {
        Log::info('PayPal order completed', [
            'order_id' => $payload['resource']['id'] ?? null,
        ]);
    }

    private function handleSubscriptionCreated(array $payload): void
    {
        Log::info('PayPal subscription created', [
            'subscription_id' => $payload['resource']['id'] ?? null,
            'plan_id' => $payload['resource']['plan_id'] ?? null,
        ]);
    }

    private function handleSubscriptionActivated(array $payload): void
    {
        Log::info('PayPal subscription activated', [
            'subscription_id' => $payload['resource']['id'] ?? null,
        ]);
    }

    private function handleSubscriptionCancelled(array $payload): void
    {
        Log::info('PayPal subscription cancelled', [
            'subscription_id' => $payload['resource']['id'] ?? null,
        ]);
    }

    private function handleInvoicePaid(array $payload): void
    {
        Log::info('PayPal invoice paid', [
            'invoice_id' => $payload['resource']['id'] ?? null,
        ]);
    }

    private function handleUnknownEvent(string $eventType, array $payload): void
    {
        Log::warning('Unknown PayPal webhook event', ['event_type' => $eventType]);
    }
}

/*
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Step 6: Define the Route
 *
 * In routes/web.php:
 */

/*
use App\Http\Controllers\PayPalWebhookController;

Route::post('/paypal-webhook', [PayPalWebhookController::class, 'handle'])
    ->middleware('validate-webhook:paypal');
*/

/*
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Step 7: Configure in PayPal Developer Dashboard
 *
 * 1. Go to PayPal Developer Dashboard → Webhooks
 * 2. Click "Add Webhook"
 * 3. Enter URL: https://your-domain.com/paypal-webhook
 * 4. Select events to subscribe to:
 *    - Payment Capture Completed
 *    - Payment Capture Denied
 *    - Checkout Order Approved
 *    - etc.
 * 5. Copy the Webhook ID and add to .env as PAYPAL_WEBHOOK_ID
 *
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Testing Your Custom Webhook
 *
 * Use PayPal's webhook simulator in the Developer Dashboard:
 * 1. Go to Webhooks → Simulate Event
 * 2. Select your webhook URL
 * 3. Choose an event type
 * 4. Click "Send Test"
 *
 * Or use curl to test locally:
 */

/*
# Example test with simplified signature
WEBHOOK_ID="your_paypal_webhook_id"
PAYLOAD='{"id":"WH-123","event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"CAP-123"}}'
CRC=$(echo -n "$PAYLOAD" | php -r "echo sprintf('%u', crc32(file_get_contents('php://stdin')));")
SIGNATURE="TX-123|2024-01-15T10:30:00Z|$WEBHOOK_ID|$CRC"

curl -X POST http://localhost:8000/paypal-webhook \
  -H "Content-Type: application/json" \
  -H "PAYPAL-TRANSMISSION-SIG: $SIGNATURE" \
  -d "$PAYLOAD"
*/

/*
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Summary: Adding a Custom Service with Strategy Pattern
 *
 * 1. Create a PayloadParser implementing PayloadParserInterface
 *    - extractEventType(): Parse event type from payload
 *    - extractMetadata(): Extract relevant metadata
 *    - serviceName(): Return service identifier
 *
 * 2. Create a SignatureValidator implementing SignatureValidatorInterface
 *    - validate(): Verify the webhook signature
 *    - serviceName(): Return service identifier
 *
 * 3. Register in WebhookService enum:
 *    - Add new case
 *    - Add parser() mapping
 *    - Add signatureValidator() mapping
 *    - Add signatureHeader() mapping
 *
 * 4. Add configuration in config/larawebhook.php
 *
 * 5. Create controller and route with validate-webhook:{service} middleware
 *
 * Supported services out of the box:
 * ✅ Stripe
 * ✅ GitHub
 * ✅ Slack
 * ✅ Shopify
 *
 * This pattern works for any additional webhook service:
 * - PayPal (this example)
 * - Mailchimp → HMAC-SHA256 validation
 * - SendGrid → HTTP Basic Auth or ECDSA signature
 * - Twilio → SHA1 signature validation
 * - Square → HMAC-SHA256 validation
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
