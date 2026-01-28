<?php

/**
 * Custom Service Webhook Example (Shopify)
 *
 * This example shows how to add support for a custom webhook service
 * using LaraWebhook's extensible Strategy Pattern architecture.
 *
 * The same pattern applies to any service:
 * - Mailchimp
 * - SendGrid
 * - Twilio
 * - PayPal
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
 * Implement PayloadParserInterface to handle Shopify's payload format.
 */
class ShopifyPayloadParser implements PayloadParserInterface
{
    /**
     * Extract the event type from Shopify payload.
     *
     * Shopify uses a "topic" header, but sometimes includes it in payload too.
     */
    public function extractEventType(array $data): string
    {
        // Shopify primarily uses headers for topic, but we can use domain as fallback
        return $data['topic'] ?? 'unknown';
    }

    /**
     * Extract metadata from Shopify payload.
     */
    public function extractMetadata(array $data): array
    {
        return [
            'shop_id' => $data['shop_id'] ?? null,
            'shop_domain' => $data['shop_domain'] ?? null,
            'order_id' => $data['id'] ?? null,
            'customer_id' => $data['customer']['id'] ?? null,
            'total_price' => $data['total_price'] ?? null,
        ];
    }

    public function serviceName(): string
    {
        return 'shopify';
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
 * Implement SignatureValidatorInterface for Shopify's HMAC-SHA256 validation.
 */
class ShopifySignatureValidator implements SignatureValidatorInterface
{
    /**
     * Validate Shopify webhook signature.
     *
     * Shopify uses base64-encoded HMAC-SHA256.
     *
     * @throws InvalidSignatureException
     */
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        $calculatedSignature = base64_encode(
            hash_hmac('sha256', $payload, $secret, true)
        );

        if (! hash_equals($calculatedSignature, $signature)) {
            throw new InvalidSignatureException('Invalid Shopify webhook signature.');
        }

        return true;
    }

    public function serviceName(): string
    {
        return 'shopify';
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
 *     case Shopify = 'shopify';  // Add new case
 *
 *     public function parser(): PayloadParserInterface
 *     {
 *         return match ($this) {
 *             self::Stripe => new StripePayloadParser,
 *             self::Github => new GithubPayloadParser,
 *             self::Shopify => new ShopifyPayloadParser,  // Add mapping
 *         };
 *     }
 *
 *     public function signatureValidator(): SignatureValidatorInterface
 *     {
 *         return match ($this) {
 *             self::Stripe => new StripeSignatureValidator,
 *             self::Github => new GithubSignatureValidator,
 *             self::Shopify => new ShopifySignatureValidator,  // Add mapping
 *         };
 *     }
 *
 *     public function signatureHeader(): string
 *     {
 *         return match ($this) {
 *             self::Stripe => 'Stripe-Signature',
 *             self::Github => 'X-Hub-Signature-256',
 *             self::Shopify => 'X-Shopify-Hmac-Sha256',  // Add header
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
    'shopify' => [
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
],
*/

/*
 * In .env:
 *
 * SHOPIFY_WEBHOOK_SECRET=your_shopify_webhook_secret
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
class ShopifyWebhookController extends Controller
{
    /**
     * Handle Shopify webhooks.
     *
     * Route: POST /shopify-webhook
     * Middleware: validate-webhook:shopify
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $topic = $request->header('X-Shopify-Topic');

        Log::info('Shopify webhook received', [
            'topic' => $topic,
            'shop' => $request->header('X-Shopify-Shop-Domain'),
        ]);

        // Route to specific handlers based on topic
        match ($topic) {
            'orders/create' => $this->handleOrderCreate($payload),
            'orders/updated' => $this->handleOrderUpdated($payload),
            'orders/cancelled' => $this->handleOrderCancelled($payload),
            'products/create' => $this->handleProductCreate($payload),
            'products/update' => $this->handleProductUpdate($payload),
            'products/delete' => $this->handleProductDelete($payload),
            'customers/create' => $this->handleCustomerCreate($payload),
            'customers/update' => $this->handleCustomerUpdate($payload),
            default => $this->handleUnknownTopic($topic, $payload),
        };

        return response()->json(['status' => 'success']);
    }

    private function handleOrderCreate(array $payload): void
    {
        Log::info('Shopify order created', [
            'order_id' => $payload['id'] ?? null,
            'total_price' => $payload['total_price'] ?? null,
        ]);

        // Example: Sync order to your database
        // Order::create([
        //     'shopify_order_id' => $payload['id'],
        //     'customer_email' => $payload['email'],
        //     'total' => $payload['total_price'],
        //     'status' => $payload['financial_status'],
        // ]);
    }

    private function handleOrderUpdated(array $payload): void
    {
        Log::info('Shopify order updated', ['order_id' => $payload['id'] ?? null]);
    }

    private function handleOrderCancelled(array $payload): void
    {
        Log::info('Shopify order cancelled', ['order_id' => $payload['id'] ?? null]);
    }

    private function handleProductCreate(array $payload): void
    {
        Log::info('Shopify product created', [
            'product_id' => $payload['id'] ?? null,
            'title' => $payload['title'] ?? null,
        ]);
    }

    private function handleProductUpdate(array $payload): void
    {
        Log::info('Shopify product updated', ['product_id' => $payload['id'] ?? null]);
    }

    private function handleProductDelete(array $payload): void
    {
        Log::info('Shopify product deleted', ['product_id' => $payload['id'] ?? null]);
    }

    private function handleCustomerCreate(array $payload): void
    {
        Log::info('Shopify customer created', [
            'customer_id' => $payload['id'] ?? null,
            'email' => $payload['email'] ?? null,
        ]);
    }

    private function handleCustomerUpdate(array $payload): void
    {
        Log::info('Shopify customer updated', ['customer_id' => $payload['id'] ?? null]);
    }

    private function handleUnknownTopic(?string $topic, array $payload): void
    {
        Log::warning('Unknown Shopify webhook topic', ['topic' => $topic]);
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
use App\Http\Controllers\ShopifyWebhookController;

Route::post('/shopify-webhook', [ShopifyWebhookController::class, 'handle'])
    ->middleware('validate-webhook:shopify');
*/

/*
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Step 7: Configure in Shopify Admin
 *
 * 1. Go to Shopify Admin → Settings → Notifications → Webhooks
 * 2. Click "Create webhook"
 * 3. Select the event (e.g., "Order creation")
 * 4. Format: JSON
 * 5. URL: https://your-domain.com/shopify-webhook
 * 6. Webhook API version: Select the latest
 * 7. Copy the webhook signing secret and add to .env
 *
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Testing Your Custom Webhook
 *
 * 1. Use Shopify CLI to forward webhooks locally:
 *    shopify webhook trigger --topic orders/create --api-version 2024-01 \
 *      --delivery-method http --address https://your-domain.com/shopify-webhook
 *
 * 2. Or use curl to test:
 */

/*
# Generate signature
SECRET="your_shopify_secret"
PAYLOAD='{"id":123456789,"email":"customer@example.com","total_price":"99.99"}'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" -binary | base64)

curl -X POST http://localhost:8000/shopify-webhook \
  -H "Content-Type: application/json" \
  -H "X-Shopify-Topic: orders/create" \
  -H "X-Shopify-Shop-Domain: your-shop.myshopify.com" \
  -H "X-Shopify-Hmac-Sha256: $SIGNATURE" \
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
 * This pattern works for any webhook service:
 * - Mailchimp → HMAC-SHA256 validation
 * - SendGrid → HTTP Basic Auth or ECDSA signature
 * - Twilio → SHA1 signature validation
 * - PayPal → Certificate-based or HMAC validation
 * - Square → HMAC-SHA256 validation
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
