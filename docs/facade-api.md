# Facade & Enum API

LaraWebhook provides a powerful Facade and an Enum for type-safe service handling.

## Larawebhook Facade

The `Larawebhook` facade provides a fluent API for all webhook operations.

### Validation

```php
use Proxynth\Larawebhook\Facades\Larawebhook;

// Validate a webhook
Larawebhook::validate($payload, $signature, 'stripe');

// Validate and log
$log = Larawebhook::validateAndLog($payload, $signature, 'github', 'push');
```

### Logging

```php
// Log webhooks manually
Larawebhook::logSuccess('stripe', 'payment.succeeded', $payload);
Larawebhook::logFailure('stripe', 'payment.failed', $payload, 'Card declined');
```

### Query Logs

```php
// Get all logs
$allLogs = Larawebhook::logs();

// Filter by service
$stripeLogs = Larawebhook::logsForService('stripe');

// Filter by status
$failedLogs = Larawebhook::failedLogs();
$successLogs = Larawebhook::successfulLogs();
```

### Notifications

```php
// Send notification if threshold reached
Larawebhook::sendNotificationIfNeeded('stripe', 'payment.failed');

// Check notification status
Larawebhook::notificationsEnabled(); // true/false
Larawebhook::getNotificationChannels(); // ['mail', 'slack']
```

### Configuration Helpers

```php
// Get webhook secret
Larawebhook::getSecret('stripe');

// Check if service is supported
Larawebhook::isServiceSupported('stripe'); // true
Larawebhook::isServiceSupported('unknown'); // false

// Get all supported services
Larawebhook::supportedServices(); // ['stripe', 'github', 'slack', 'shopify']
```

## WebhookService Enum

The `WebhookService` enum centralizes all service-related configuration.

### Available Services

```php
use Proxynth\Larawebhook\Enums\WebhookService;

WebhookService::Stripe;  // 'stripe'
WebhookService::Github;  // 'github'
WebhookService::Slack;   // 'slack'
WebhookService::Shopify; // 'shopify'
```

### Signature Headers

```php
WebhookService::Stripe->signatureHeader();  // 'Stripe-Signature'
WebhookService::Github->signatureHeader();  // 'X-Hub-Signature-256'
WebhookService::Slack->signatureHeader();   // 'X-Slack-Signature'
WebhookService::Shopify->signatureHeader(); // 'X-Shopify-Hmac-Sha256'
```

### Get Secret from Config

```php
WebhookService::Stripe->secret(); // Returns configured secret
```

### Payload Parsers

```php
// Get the parser for extracting event types and metadata
$parser = WebhookService::Stripe->parser();

$eventType = $parser->extractEventType($payload);
$metadata = $parser->extractMetadata($payload);
```

### Signature Validators

```php
// Get the validator for signature verification
$validator = WebhookService::Stripe->signatureValidator();

$isValid = $validator->validate($payload, $signature, $secret, $tolerance);
```

### Check Support

```php
WebhookService::isSupported('stripe');  // true
WebhookService::isSupported('unknown'); // false
```

### Convert from String

```php
// Safe conversion (returns null if invalid)
$service = WebhookService::tryFromString('stripe'); // WebhookService::Stripe

// Throws on invalid
$service = WebhookService::fromString('stripe'); // WebhookService::Stripe
$service = WebhookService::fromString('invalid'); // throws ValueError
```

### Validation Rules

```php
// Useful for Laravel validation
WebhookService::values(); // ['stripe', 'github', 'slack', 'shopify']
WebhookService::validationRule(); // ['stripe', 'github', 'slack', 'shopify']

// In a form request
'service' => ['required', 'in:' . implode(',', WebhookService::values())],
```

## Using Enum with Facade

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

## Benefits of Using the Enum

- **Type Safety**: IDE autocompletion and static analysis support
- **Centralized Configuration**: All service-related config in one place
- **DRY Principle**: No duplicated service strings
- **Easy Extension**: Add a new service by adding a case to the enum
