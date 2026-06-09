# Security and Privacy Policy

LaraWebhook is an experimental Laravel package focused on webhook validation, logging, replay, idempotency and safer 
payload handling.  
Webhook payloads may contain personal, sensitive, business-critical or security-related data. This document explains 
how LaraWebhook approaches security and privacy, which data may be processed, and which responsibilities remain on the 
application using the package.  
LaraWebhook does not make an application compliant by itself. Compliance depends on your application, configuration, 
infrastructure, contracts, data categories, retention policy and operational procedures.

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take security issues seriously. We appreciate your efforts to responsibly disclose your findings.

### How to Report

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to: **proxynth.tech@gmail.com**

Include the following information in your report:

- Type of issue (e.g., signature bypass, injection, information disclosure)
- Full paths of source file(s) related to the issue
- Location of the affected source code (tag/branch/commit or direct URL)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

### What to Expect

- **Acknowledgment**: We will acknowledge receipt of your vulnerability report within 48 hours.
- **Communication**: We will keep you informed about our progress toward resolving the issue.
- **Timeline**: We aim to resolve critical vulnerabilities within 7 days.
- **Credit**: We will credit you in the release notes (unless you prefer to remain anonymous).

### Scope

The following are in scope for security reports:

- Signature validation bypass
- Authentication/authorization issues
- Injection vulnerabilities
- Information disclosure
- Cryptographic issues

### Out of Scope

- Issues in dependencies (please report to the respective project)
- Issues requiring physical access to the server
- Social engineering attacks
- Denial of service attacks

## Security Best Practices

When using LaraWebhook, please follow these security best practices:

1. **Always use HTTPS** in production for webhook endpoints
2. **Keep secrets secure** - never commit webhook secrets to version control
3. **Use environment variables** for all sensitive configuration
4. **Regularly rotate** webhook secrets
5. **Monitor logs** for suspicious webhook activity
6. **Keep the package updated** to receive security patches

## Security Updates

Security updates will be released as patch versions and announced in:

- GitHub Releases
- CHANGELOG.md
- GitHub Security Advisories (for critical issues)

Thank you for helping keep LaraWebhook and its users safe!


## Guiding principles

LaraWebhook is designed around the following principles:
- do not expose debugging or replay features by default;
- avoid storing full webhook payloads unless explicitly required;
- redact sensitive fields before persistence when redacted storage is enabled;
- provide configurable retention for webhook logs;
- make replay explicit and restricted;
- keep provider secrets outside the codebase;
- let the host application remain in control of authentication, authorization and data retention.

## Data processed by LaraWebhook

Depending on your configuration, LaraWebhook may process or store the following data.

### Webhook metadata

LaraWebhook may store operational metadata such as:
- webhook service or provider name;
- external event ID when available;
- event type;
- validation status;
- processing status;
- attempt count;
- error message;
- timestamps;
- idempotency key or fallback payload hash.

This metadata is used for debugging, duplicate detection, replay tracking and operational visibility.

### Webhook payload

Webhook payloads are the JSON bodies received from providers such as Stripe, GitHub, Slack, Shopify or custom services.

Depending on the provider and event type, payloads may contain:
- email addresses;
- names;
- phone numbers;
- postal addresses;
- customer identifiers;
- payment references;
- order information;
- tokens or secrets accidentally included by upstream systems;
- internal business metadata.

For this reason, payload storage must be configured carefully.

### Provider secrets

LaraWebhook uses provider webhook secrets to validate signatures.

Secrets should be stored in environment variables or a secret manager. They should never be committed to the repository or exposed in logs, dashboards, exceptions or client responses.

## Payload storage modes

LaraWebhook supports configurable payload storage modes.

```php
'payload_storage' => [
    'mode' => env('LARAWEBHOOK_PAYLOAD_STORAGE_MODE', 'redacted'),
],
```

Supported modes:

| Mode     | Description                                                                               |
|----------|-------------------------------------------------------------------------------------------|
| none     | Do not store the webhook payload. Only metadata is persisted.                             |
| redacted | Store a sanitized version of the payload. Sensitive fields are masked before persistence. |
| full | Store the full payload. Useful for debugging and replay, but should be explicitly enabled only when required. |

**none**

In none mode, LaraWebhook does not persist the webhook payload.

This is the strictest mode from a data minimization perspective.

Consequences:
* replay is not available for logs without a stored payload;
* debugging relies on metadata, status, event type and errors;
* less sensitive data is persisted by the application.

Use this mode when you do not need replay or payload-level inspection.

**redacted**

In redacted mode, LaraWebhook stores a sanitized payload.

Sensitive fields are replaced before persistence according to the configured redaction rules.

This mode is useful when you need some payload visibility for debugging, but do not want to store obvious sensitive fields in clear text.

Consequences:
* the stored payload may not be equivalent to the original provider payload;
* replaying a redacted payload may not behave like replaying the original payload;
* custom provider payloads should be reviewed to ensure redaction rules are sufficient.

**full**

In full mode, LaraWebhook stores the full payload.

This mode can be useful for debugging and replay, but it may store personal, sensitive or business-critical data depending on the provider payload.

Use this mode carefully.

Recommended safeguards:
* enable it only when required;
* restrict access to logs and replay;
* configure a short retention period;
* avoid exposing full payloads in dashboards or API responses unless access is controlled;
* review your legal and internal data retention requirements.

### Sensitive data redaction

LaraWebhook includes a payload redaction service that masks configured sensitive fields before storage.

Example configuration:
```php
'redaction' => [
    'fields' => [
        'email',
        'phone',
        'address',
        'token',
        'secret',
        'authorization',
        'client_secret',
        'password',
        'api_key',
        'access_token',
        'refresh_token',
    ],
    'replacement' => '[REDACTED]',
],
```

Redaction is:

* recursive;
* case-insensitive;
* based on field names;
* applied before persistence when `payload_strage.mode` is set to `redacted`.

Example original payload:
```json
{
  "customer": {
    "email": "client@example.com",
    "phone": "+33612345678"
  },
  "payment": {
    "amount": 4900,
    "client_secret": "pi_123_secret_abc"
  }
}
```

Example redacted payload: 
```json
{
  "customer": {
    "email": "[REDACTED]",
    "phone": "[REDACTED]"
  },
  "payment": {
    "amount": 4900,
    "client_secret": "[REDACTED]"
  }
}
```

#### Redaction limitations

Redaction is a safety mechanism, not a complete anonymization system.

You should not assume that redacted payloads are anonymous.

Redaction rules are based on configured field names. Provider-specific fields, nested structures or unexpected payload formats may require additional configuration or custom handling.

The application owner is responsible for reviewing provider payloads and configuring suitable redaction rules.

## Retention policy

Webhook logs should not be kept forever by default.

LaraWebhook provides a retention configuration:
```php
'retention' => [
    'enabled' => env('LARAWEBHOOK_RETENTION_ENABLED', true),
    'days' => (int) env('LARAWEBHOOK_RETENTION_DAYS', 30),
],
```

When retention is enabled, old webhook logs can be pruned with:
```bash
php artisan larawebhook:prune
```

You can override the retention duration:
```bash
php artisan larawebhook:prune --older-than=7d
```

You can preview deletions without deleting anything:
```bash
php artisan larawebhook:prune --older-than=30d --dry-run
```

You can schedule pruning in your Laravel application:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('larawebhook:prune')->daily();
```

You are responsible for choosing a retention period that matches your debugging needs, payload storage mode, legal obligations, contractual requirements and internal data retention policy.

### Replay

Replay allows a previously logged webhook to be processed again.

Replay can be useful for:
* recovering from temporary application failures;
* reprocessing events after a bug fix;
* debugging provider integration issues;
* manually retrying failed events.

Replay can also be risky.

Replaying a webhook may trigger business actions again, such as:
* activating an account;
* updating an order;
* provisioning a resource;
* sending a notification;
* changing billing state.

Recommended safeguards:
* restrict replay access to trusted users;
* protect API replay endpoints with authentication and authorization;
* ensure downstream handlers are idempotent;
* log replay attempts;
* avoid replaying events when the original payload is not available;
* use caution when replaying redacted payloads.

#### Replay and payload storage

Replay requires a stored payload.

When `payload_storage.mode` is set to `none`, LaraWebhook does not persist the webhook payload. Replay is therefore unavailable for those logs.

When `payload_storage.mode` is set to `redacted`, the stored payload may differ from the original provider payload. Replaying a redacted payload may fail or produce different behavior.

When `payload_storage.mode` is set to `full`, replay can use the full stored payload, but this mode requires stronger access control and retention discipline.

### Dashboard and API access

LaraWebhook dashboard and API routes can expose operational data, errors, metadata and possibly payloads depending on your configuration.

They should not be exposed publicly.

Recommended configuration for dashboard routes:
```php
'dashboard' => [
    'enabled' => env('LARAWEBHOOK_DASHBOARD_ENABLED', false),
    'path' => env('LARAWEBHOOK_DASHBOARD_PATH', 'larawebhook/dashboard'),
    'middleware' => ['web', 'auth'],
],
```

Recommended configuration for API routes:
```php
'api' => [
    'enabled' => env('LARAWEBHOOK_API_ENABLED', false),
    'path' => env('LARAWEBHOOK_API_PATH', 'api/larawebhook'),
    'middleware' => ['api', 'auth:sanctum'],
],
```

You may also use authorization gates or custom middleware:
```php
'middleware' => ['web', 'auth', 'can:manageLaraWebhook'],
```

or: 

```php
'middleware' => ['api', 'auth:sanctum', 'can:manageLaraWebhook'],
```

### Idempotency

Webhook providers may deliver the same event more than once.

LaraWebhook uses an idempotency resolver to detect duplicate webhook events.

By default:
* if a provider external event ID is available, it is used as the idempotency key;
* if no external ID is available, LaraWebhook may fall back to a deterministic payload hash.

The payload hash is derived from a normalized payload and does not require storing the raw payload.

Downstream application handlers should also be idempotent. Package-level duplicate detection does not replace application-level safety.

### User responsibilities

The application owner is responsible for configuring LaraWebhook according to their own context.

Before using LaraWebhook for production-critical or sensitive workloads, review:
* dashboard access control;
* API access control;
* replay permissions;
* provider secret management;
* payload storage mode;
* redaction rules;
* log retention period;
* pruning schedule;
* idempotency behavior;
* downstream handler idempotency;
* logging and error reporting;
* legal, contractual and compliance requirements.

LaraWebhook provides tools and safer defaults, but it does not replace a security review, legal review, data protection impact assessment, or production readiness review when those are required.

### Recommended production checklist

Before enabling LaraWebhook in production:

* Dashboard is disabled or protected by authentication.
* API routes are disabled or protected by token-based authentication.
* Replay endpoints are restricted to trusted users.
* Provider secrets are stored outside the codebase.
* Payload storage mode is explicitly chosen.
* Redaction rules are reviewed against real provider payloads.
* Retention period is configured.
* Pruning is scheduled.
* Downstream handlers are idempotent.
* Logs and payloads are not exposed to unauthorized users.
* Error messages do not leak secrets or raw payloads.
