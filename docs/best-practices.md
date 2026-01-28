# Best Practices

## Security

### Always Use HTTPS

```php
// Force HTTPS in production
if (app()->environment('production')) {
    URL::forceScheme('https');
}
```

### Always Validate Signatures

```php
// The middleware does this automatically
Route::post('/webhook', [Controller::class, 'handle'])
    ->middleware('validate-webhook:stripe');
```

### Keep Secrets in Environment Variables

```env
# .env file (NEVER commit this file)
STRIPE_WEBHOOK_SECRET=whsec_your_secret_here
GITHUB_WEBHOOK_SECRET=your_github_secret_here
```

### Rotate Secrets Regularly

1. Update secret in provider dashboard
2. Update `.env` file
3. Deploy the change
4. Delete old webhook endpoint after verifying

### Rate Limiting

```php
Route::post('/stripe-webhook', [StripeController::class, 'handle'])
    ->middleware(['validate-webhook:stripe', 'throttle:60,1']);
```

## Error Handling

### Log All Events

```php
// LaraWebhook logs automatically
// View in dashboard: /larawebhook/dashboard
```

### Handle Failures Gracefully

```php
private function handlePaymentFailed(array $payload): void
{
    try {
        $this->processPayment($payload);
    } catch (\Exception $e) {
        Log::error('Failed to process payment webhook', [
            'error' => $e->getMessage(),
            'payload' => $payload,
        ]);

        // Don't re-throw - return 200 to prevent retries
        // Use notifications for alerting
    }
}
```

### Use Try-Catch for External Calls

```php
private function handlePush(array $payload): void
{
    try {
        Http::timeout(5)->post('https://external-api.com/deploy', [
            'repository' => $payload['repository']['name'],
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to trigger deployment', [
            'error' => $e->getMessage(),
        ]);
        // Don't throw - webhook should still return 200
    }
}
```

## Performance

### Process Asynchronously with Queues

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

### Set Reasonable Timeouts

```php
// Don't let webhook processing block
set_time_limit(30);
```

### Avoid Heavy Processing in Handler

```php
// BAD: Heavy processing blocks response
public function handle(Request $request)
{
    $payload = json_decode($request->getContent(), true);
    
    // This takes 30 seconds
    $this->processLargeReport($payload);
    
    return response()->json(['status' => 'success']);
}

// GOOD: Dispatch to queue
public function handle(Request $request)
{
    $payload = json_decode($request->getContent(), true);
    
    ProcessReport::dispatch($payload);
    
    return response()->json(['status' => 'success']);
}
```

## Monitoring

### Check for Recent Failures

```bash
php artisan tinker
>>> \Proxynth\Larawebhook\Models\WebhookLog::where('status', 'failed')
        ->where('created_at', '>', now()->subHour())
        ->count();
```

### Enable Automatic Notifications

```env
WEBHOOK_NOTIFICATIONS_ENABLED=true
WEBHOOK_NOTIFICATION_CHANNELS=mail,slack
WEBHOOK_EMAIL_RECIPIENTS=admin@example.com
WEBHOOK_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
WEBHOOK_FAILURE_THRESHOLD=3
```

### Use the Dashboard

Access at `/larawebhook/dashboard`:
- Filter by service, status, date
- Replay failed webhooks
- View detailed payloads and errors

## Idempotency

### Handle Duplicate Webhooks

Providers may send the same webhook multiple times. Handle this gracefully:

```php
private function handlePaymentSucceeded(array $payload): void
{
    $paymentIntent = $payload['data']['object'];
    
    // Use updateOrCreate to handle duplicates
    Order::updateOrCreate(
        ['stripe_payment_intent_id' => $paymentIntent['id']],
        ['status' => 'paid']
    );
}
```

### Store Webhook IDs

```php
private function handleWebhook(array $payload): void
{
    $webhookId = $payload['id'];
    
    // Check if already processed
    if (ProcessedWebhook::where('webhook_id', $webhookId)->exists()) {
        Log::info('Webhook already processed', ['id' => $webhookId]);
        return;
    }
    
    // Process and mark as done
    $this->processWebhook($payload);
    ProcessedWebhook::create(['webhook_id' => $webhookId]);
}
```

## Response Codes

### Always Return 200 for Valid Webhooks

```php
// Even if processing fails, return 200 if signature is valid
// This prevents infinite retries
return response()->json(['status' => 'success']);
```

### Let LaraWebhook Handle Invalid Signatures

The middleware automatically returns:
- **403**: Invalid signature
- **400**: Missing headers or malformed payload
- **500**: Secret not configured
