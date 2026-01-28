<?php

/**
 * Custom Service Webhook Example (Shopify)
 *
 * This example shows how to add support for a custom webhook service
 * that is not built into LaraWebhook by default.
 *
 * We'll use Shopify as an example, but the same pattern applies to any service:
 * - Mailchimp
 * - SendGrid
 * - Twilio
 * - PayPal
 * - Square
 * - etc.
 */

namespace App\Services;

use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Services\WebhookValidator;

/**
 * Step 1: Create a Custom Validator
 *
 * Extend the base WebhookValidator class and implement
 * the signature validation logic for your service.
 */
class ShopifyWebhookValidator extends WebhookValidator
{
    /**
     * Validate the Shopify webhook signature.
     *
     * Shopify uses HMAC-SHA256 with base64 encoding.
     *
     * @throws InvalidSignatureException
     */
    public function validate(string $payload, string $signature, string $service): bool
    {
        // Shopify sends the signature in the X-Shopify-Hmac-Sha256 header
        $calculatedSignature = base64_encode(
            hash_hmac('sha256', $payload, $this->secret, true)
        );

        if (! hash_equals($calculatedSignature, $signature)) {
            throw new InvalidSignatureException(
                "Invalid Shopify webhook signature. Expected: {$calculatedSignature}, Got: {$signature}"
            );
        }

        return true;
    }

    /**
     * Optional: Override the signature header name.
     */
    protected function getSignatureHeader(): string
    {
        return 'X-Shopify-Hmac-Sha256';
    }
}

/**
 * Step 2: Configure the Service
 *
 * Add to config/larawebhook.php:
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
    // Add your custom service
    'shopify' => [
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
        'tolerance' => 300, // Not used for Shopify, but required
    ],
],
*/

/**
 * Add to .env:
 */
/*
SHOPIFY_WEBHOOK_SECRET=your_shopify_webhook_secret
*/

/**
 * Step 3: Create a Service Provider (Optional but Recommended)
 *
 * Register the custom validator in a service provider.
 */

namespace App\Providers;

use App\Services\ShopifyWebhookValidator;
use Illuminate\Support\ServiceProvider;

class ShopifyWebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the Shopify validator to the container
        $this->app->bind('webhook.validator.shopify', function () {
            return new ShopifyWebhookValidator(
                config('larawebhook.services.shopify.webhook_secret')
            );
        });
    }

    public function boot(): void
    {
        // You can add additional setup here if needed
    }
}

/**
 * Don't forget to register the service provider in config/app.php:
 */
/*
'providers' => [
    // ...
    App\Providers\ShopifyWebhookServiceProvider::class,
],
*/

/**
 * Step 4: Create a Custom Middleware (Option A - Recommended)
 *
 * Create a middleware specifically for Shopify webhooks.
 */

namespace App\Http\Middleware;

use App\Services\ShopifyWebhookValidator;
use Closure;
use Illuminate\Http\Request;
use Proxynth\Larawebhook\Services\WebhookLogger;
use Symfony\Component\HttpFoundation\Response;

class ValidateShopifyWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Shopify-Hmac-Sha256');
        $topic = $request->header('X-Shopify-Topic'); // Shopify's event type

        if (! $signature) {
            return response('Missing Shopify signature', 400);
        }

        try {
            $validator = new ShopifyWebhookValidator(
                config('larawebhook.services.shopify.webhook_secret')
            );

            // Validate the signature
            $validator->validate($payload, $signature, 'shopify');

            // Log the webhook
            $logger = new WebhookLogger;
            $logger->logSuccess('shopify', $topic ?? 'unknown', json_decode($payload, true) ?? []);

            return $next($request);
        } catch (\Exception $e) {
            // Log the failure
            $logger = new WebhookLogger;
            $logger->logFailure(
                'shopify',
                $topic ?? 'unknown',
                json_decode($payload, true) ?? [],
                $e->getMessage()
            );

            return response($e->getMessage(), 403);
        }
    }
}

/**
 * Register the middleware in app/Http/Kernel.php:
 */
/*
protected $middlewareAliases = [
    // ...
    'validate-shopify-webhook' => \App\Http\Middleware\ValidateShopifyWebhook::class,
];
*/

/**
 * Step 5: Create the Controller
 */

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    /**
     * Handle Shopify webhooks.
     *
     * Add to routes/web.php:
     *   Route::post('/shopify-webhook', [ShopifyWebhookController::class, 'handle'])
     *       ->middleware('validate-shopify-webhook');
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
        Log::info('Shopify order updated', [
            'order_id' => $payload['id'] ?? null,
        ]);
    }

    private function handleOrderCancelled(array $payload): void
    {
        Log::info('Shopify order cancelled', [
            'order_id' => $payload['id'] ?? null,
        ]);
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
        Log::info('Shopify product updated', [
            'product_id' => $payload['id'] ?? null,
        ]);
    }

    private function handleProductDelete(array $payload): void
    {
        Log::info('Shopify product deleted', [
            'product_id' => $payload['id'] ?? null,
        ]);
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
        Log::info('Shopify customer updated', [
            'customer_id' => $payload['id'] ?? null,
        ]);
    }

    private function handleUnknownTopic(string $topic, array $payload): void
    {
        Log::warning('Unknown Shopify webhook topic', [
            'topic' => $topic,
        ]);
    }
}

/**
 * Step 6: Configure in Shopify Admin
 *
 * 1. Go to Shopify Admin → Settings → Notifications → Webhooks
 * 2. Click "Create webhook"
 * 3. Select the event (e.g., "Order creation")
 * 4. Format: JSON
 * 5. URL: https://your-domain.com/shopify-webhook
 * 6. Webhook API version: Select the latest
 * 7. Copy the webhook signing secret and add to .env
 */

/**
 * Alternative: Using the Generic Middleware (Option B - Simpler)
 *
 * If you don't want to create a custom middleware, you can use the generic
 * validate-webhook middleware with a custom validator.
 */

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register a custom validator for the validate-webhook middleware
        Route::middlewareGroup('validate-webhook:shopify', function () {
            // This is a simplified approach - you'd need to extend the middleware
            // or use the custom middleware approach shown above
        });
    }
}

/**
 * Testing Your Custom Webhook
 *
 * 1. Use Shopify CLI to forward webhooks locally:
 *    shopify webhook trigger --topic orders/create --api-version 2024-01 \
 *      --delivery-method http --address https://your-domain.com/shopify-webhook
 *
 * 2. Or use curl to test:
 */
/*
curl -X POST http://localhost:8000/shopify-webhook \
  -H "Content-Type: application/json" \
  -H "X-Shopify-Topic: orders/create" \
  -H "X-Shopify-Shop-Domain: your-shop.myshopify.com" \
  -H "X-Shopify-Hmac-Sha256: YOUR_CALCULATED_SIGNATURE" \
  -d '{
    "id": 123456789,
    "email": "customer@example.com",
    "total_price": "99.99",
    "financial_status": "paid"
  }'
*/

/**
 * Summary: Steps to Add Any Custom Service
 *
 * 1. Create a custom validator extending WebhookValidator
 * 2. Implement the validate() method with your service's signature logic
 * 3. Add service configuration to config/larawebhook.php
 * 4. Create a custom middleware or use the generic one
 * 5. Create a controller to handle webhook events
 * 6. Add routes with your middleware
 * 7. Configure webhooks in the service's dashboard
 * 8. Test with real webhooks or curl
 *
 * This pattern works for any webhook service:
 * - Mailchimp → HMAC-SHA256 validation
 * - SendGrid → HTTP Basic Auth or signature
 * - Twilio → SHA1 signature validation
 * - PayPal → Certificate-based validation
 * - Square → HMAC-SHA256 validation
 * - etc.
 */
