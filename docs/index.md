---
layout: home

hero:
  name: LaraWebhook
  text: Secure Webhook Handling for Laravel
  tagline: Validate signatures, manage retries, log events, and integrate popular services in minutes.
  actions:
    - theme: brand
      text: Get Started
      link: /getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/proxynth/larawebhook

features:
  - icon: 🔒
    title: Signature Validation
    details: Verify webhook authenticity for Stripe, GitHub, Slack, Shopify, and custom services.
  - icon: 🔄
    title: Retry Management
    details: Automatically retry failed webhooks with exponential backoff.
  - icon: 📊
    title: Dashboard & API
    details: Modern UI with Alpine.js and Tailwind CSS. REST API for programmatic access.
  - icon: 🔔
    title: Failure Notifications
    details: Get alerted via Email and Slack when webhooks fail repeatedly.
  - icon: 🎯
    title: Type-Safe API
    details: WebhookService enum for IDE autocompletion and type safety.
  - icon: 🧩
    title: Extensible
    details: Strategy Pattern architecture - add new services in minutes.
---

## Quick Example

```php
// routes/web.php
Route::post('/stripe-webhook', function () {
    $payload = json_decode(request()->getContent(), true);
    
    // Handle the event
    event(new StripeWebhookReceived($payload));
    
    return response()->json(['status' => 'success']);
})->middleware('validate-webhook:stripe');
```

**What the middleware does automatically:**
- ✅ Validates the webhook signature
- ✅ Logs the event to the database
- ✅ Returns 403 for invalid signatures
- ✅ Returns 400 for malformed payloads

## Supported Services

| Service | Signature Header | Status |
|---------|-----------------|--------|
| **Stripe** | `Stripe-Signature` | ✅ Built-in |
| **GitHub** | `X-Hub-Signature-256` | ✅ Built-in |
| **Slack** | `X-Slack-Signature` | ✅ Built-in |
| **Shopify** | `X-Shopify-Hmac-Sha256` | ✅ Built-in |
| **Custom** | Any | ✅ Extensible |
