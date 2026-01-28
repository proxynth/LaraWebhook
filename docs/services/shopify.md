# Shopify Integration

## Configuration

Add your Shopify webhook secret to `.env`:

```env
SHOPIFY_WEBHOOK_SECRET=your_shopify_webhook_secret_here
```

## Route Setup

```php
// routes/web.php
use App\Http\Controllers\ShopifyWebhookController;

Route::post('/shopify-webhook', [ShopifyWebhookController::class, 'handle'])
    ->middleware('validate-webhook:shopify');
```

## Controller Example

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
            default => $this->handleUnknown($topic),
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

        // Sync order to your database
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

    private function handleUnknown(?string $topic): void
    {
        Log::warning('Shopify: Unknown topic', ['topic' => $topic]);
    }
}
```

## Signature Validation

Shopify uses Base64-encoded HMAC-SHA256:

```
X-Shopify-Hmac-Sha256: Base64(HMAC-SHA256(secret, body))
```

LaraWebhook automatically validates this signature.

## Configure in Shopify Admin

1. Go to **Settings** → **Notifications** → **Webhooks**
2. Click **Create webhook**
3. Select the event (e.g., `Order creation`)
4. Format: **JSON**
5. URL: `https://your-domain.com/shopify-webhook`
6. API version: Select the latest stable
7. Click **Save**
8. Copy the webhook signing secret to your `.env`

## Configure via API

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

## Testing with Shopify CLI

```bash
# Install Shopify CLI
npm install -g @shopify/cli @shopify/theme

# Test webhook delivery
shopify webhook trigger --topic orders/create \
  --api-version 2024-01 \
  --delivery-method http \
  --address https://your-domain.com/shopify-webhook
```

## Common Topics

| Topic | Description |
|-------|-------------|
| `orders/create` | New order placed |
| `orders/updated` | Order modified |
| `orders/cancelled` | Order cancelled |
| `orders/fulfilled` | Order shipped |
| `orders/paid` | Order paid |
| `products/create` | Product created |
| `products/update` | Product modified |
| `products/delete` | Product deleted |
| `customers/create` | New customer |
| `customers/update` | Customer modified |
| `refunds/create` | Refund issued |
| `inventory_levels/update` | Stock changed |

## Important Headers

Shopify sends useful headers with each webhook:

| Header | Description |
|--------|-------------|
| `X-Shopify-Topic` | Webhook topic |
| `X-Shopify-Shop-Domain` | Shop domain |
| `X-Shopify-API-Version` | API version |
| `X-Shopify-Hmac-Sha256` | Signature |
| `X-Shopify-Webhook-Id` | Unique webhook ID |
