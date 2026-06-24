# Architecture

LaraWebhook is organized around four layers:

- `Domain`: business rules, entities, value objects, and domain events.
- `Application`: use cases, ports, commands, results, and read models.
- `Infrastructure`: Laravel adapters, Eloquent persistence, controllers, middleware, jobs, and service bindings.
- `Shared`: cross-cutting primitives such as the facade, the event bus port, and package-level wiring.

## Recommended Flows

### Middleware flow

Use `validate-webhook:{service}` for the default inbound flow.

1. `ValidateWebhook` checks the signature with `SignatureValidator`.
2. `ReceiveWebhook` resolves the event type, idempotency key, and external id.
3. `ReceiveWebhook` checks duplicates through `WebhookDuplicateDetector`.
4. `ReceiveWebhook` validates the payload through `ValidateWebhook`.
5. `ReceiveWebhook` records the audit log through `RecordWebhookLog`.
6. The Laravel adapter dispatches collected domain events through the application event bus.

This is the recommended path for inbound webhooks.

### Manual facade flow

Use the facade for controlled manual orchestration:

```php
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Facades\Larawebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;

$signature = Signature::fromString($request->header('Stripe-Signature'));
$log = Larawebhook::validateAndLog($request->getContent(), $signature, 'stripe', 'payment_intent.succeeded');
```

`Signature::fromString()` wraps the raw header in a typed value object. For providers that include timestamp metadata, the timestamp stays attached to the signature object.

## Application Roles

### ValidateWebhook

`ValidateWebhook` is the application boundary for signature verification. It accepts a parsed payload, a service, a signature, the event name, the external id, and the secret. It returns a simple validation result and does not write persistence itself.

### RecordWebhookLog

`RecordWebhookLog` is the application boundary for audit logging. It delegates persistence to the audit writer port and returns the stored `WebhookLog`.

### ReceiveWebhook

`ReceiveWebhook` orchestrates the inbound webhook flow. It resolves the event type, computes the idempotency key, rejects duplicates, validates the signature, records the audit log, and returns domain events alongside the result.

## Identity and Deduplication

- `external_id` stores the provider delivery or event identifier when the provider exposes one.
- `idempotency_key` stores the deduplication key used by the application.
- Providers without a stable external id use a payload-hash fallback for idempotency.

The database unique constraint applies to `service + idempotency_key`, not `service + external_id`.

## Read Side

Dashboard and API query handlers use read models and read repositories:

- `WebhookLogSummary`
- `WebhookLogDetails`
- `WebhookFailureDetails`

These types stay in `Application` so the read side remains framework-agnostic.

## Retry Behavior

LaraWebhook supports both retry modes:

- **Sync retry**: the request flow retries inline before returning.
- **Async retry**: the middleware returns `202 Accepted` and dispatches `RetryWebhookJob`.

The retry flow keeps the application decision in the use case and leaves queue dispatch to the Laravel adapter.

## Payload Storage

Payload storage is configurable:

- `none`: store no payload
- `redacted`: store a sanitized payload
- `full`: store the raw payload for debugging and replay

Choose the least permissive mode that still supports your operational needs.

## Slack Signatures

Slack signatures use a timestamped HMAC scheme. The incoming request includes:

- `X-Slack-Signature`
- `X-Slack-Request-Timestamp`

The validator uses both values so timestamp tolerance can be enforced and replay protection stays in place.

## Facade Guidance

Recommended facade methods:

- `validate()`
- `validateAndLog()`
- `validateWithRetries()`
- read helpers such as `logs()`, `logsForService()`, `failedLogs()`, `successfulLogs()`

Legacy helpers:

- `logSuccess()` - deprecated
- `logFailure()` - deprecated

These legacy helpers remain available for compatibility, but they go through the application logging flow and should not be the default choice for new code.
