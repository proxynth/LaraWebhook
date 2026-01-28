# Service Integrations

LaraWebhook supports multiple webhook providers out of the box.

## Built-in Services

| Service | Signature Header | Algorithm |
|---------|-----------------|-----------|
| [Stripe](/services/stripe) | `Stripe-Signature` | HMAC-SHA256 with timestamp |
| [GitHub](/services/github) | `X-Hub-Signature-256` | HMAC-SHA256 |
| [Slack](/services/slack) | `X-Slack-Signature` | HMAC-SHA256 with timestamp |
| [Shopify](/services/shopify) | `X-Shopify-Hmac-Sha256` | Base64 HMAC-SHA256 |

## Quick Setup

### 1. Add Secret to `.env`

```env
STRIPE_WEBHOOK_SECRET=whsec_xxx
GITHUB_WEBHOOK_SECRET=xxx
SLACK_WEBHOOK_SECRET=xxx
SHOPIFY_WEBHOOK_SECRET=xxx
```

### 2. Create Route with Middleware

```php
Route::post('/stripe-webhook', [StripeController::class, 'handle'])
    ->middleware('validate-webhook:stripe');

Route::post('/github-webhook', [GithubController::class, 'handle'])
    ->middleware('validate-webhook:github');

Route::post('/slack-webhook', [SlackController::class, 'handle'])
    ->middleware('validate-webhook:slack');

Route::post('/shopify-webhook', [ShopifyController::class, 'handle'])
    ->middleware('validate-webhook:shopify');
```

### 3. Handle the Webhook

```php
public function handle(Request $request): JsonResponse
{
    // Webhook is already validated by middleware
    $payload = json_decode($request->getContent(), true);
    
    // Process your webhook
    // ...
    
    return response()->json(['status' => 'success']);
}
```

## Adding Custom Services

LaraWebhook uses the Strategy Pattern for extensibility. See [Extending LaraWebhook](/extending) to add support for any webhook provider.

## Validation Flow

```
┌─────────────────┐     ┌──────────────────────┐     ┌─────────────────────┐
│                 │     │                      │     │                     │
│  Provider       │────▶│  LaraWebhook         │────▶│  Your Application   │
│  (Webhook)      │     │  - Validates sig     │     │  - Process event    │
│                 │     │  - Logs event        │     │  - Update database  │
└─────────────────┘     │  - Returns response  │     │                     │
                        └──────────────────────┘     └─────────────────────┘
                                  │
                                  │ Invalid
                                  ▼
                        ┌──────────────────────┐
                        │  Returns 403/400     │
                        │  Logs failure        │
                        └──────────────────────┘
```
