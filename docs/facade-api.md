# Facade & Enum API

LaraWebhook provides a powerful Facade and an Enum for type-safe service handling.
For the inbound request flow, see [Architecture](/architecture). For manual validation, always wrap raw signature headers with `Signature::fromString()`.

## Role of the facade

The `Larawebhook` facade is a Laravel-friendly public API.

It is intentionally a DX adapter over application use cases, repositories and Laravel infrastructure services. It should not be treated as the place where core webhook workflows are implemented.

Critical behavior belongs to dedicated use cases:

- `ReceiveWebhook` for inbound middleware processing;
- `ValidateWebhook` for signature validation;
- `RecordWebhookLog` for audit persistence;
- `RetryWebhook` for retry attempts;
- `ReplayWebhook` for replay attempts.

For new code, prefer the high-level helpers:

- `validate()`
- `validateAndLog()`
- `validateWithRetries()`
- query/read helpers

Manual logging helpers remain available for compatibility, but are deprecated.

### Validation

```php
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Facades\Larawebhook;

// Validate a webhook
Larawebhook::validate($payload, $signature, 'stripe');

// Validate and log
$log = Larawebhook::validateAndLog($payload, $signature, 'github', 'push');
```

#### Logging

Manual logging helpers remain available for compatibility, but they are deprecated.

Prefer `validateAndLog()` when a webhook should be validated and persisted, or use `RecordWebhookLog` directly when writing internal package code.

```php
// Deprecated manual audit helpers
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

The `WebhookService` enum centralizes supported service identifiers and string conversion.

### Available Services

```php
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

WebhookService::Stripe;  // 'stripe'
WebhookService::Github;  // 'github'
WebhookService::Slack;   // 'slack'
WebhookService::Shopify; // 'shopify'
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

Provider-specific headers, parsers, secrets, and validators are resolved by the package internals rather than through the enum.

## Using Enum with Facade

All facade methods accept both strings and the enum:

```php
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Facades\Larawebhook;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

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
