# Production Checklist

Before using LaraWebhook in production, review and validate the following checklist.

LaraWebhook can process and store sensitive operational data, including webhook metadata and, depending on your configuration, webhook payloads. Production usage requires explicit configuration of access control, payload storage, retention, replay permissions and provider secrets.

## Access control

### Dashboard

- [ ] The dashboard is disabled if it is not needed in production.
- [ ] If enabled, the dashboard is protected by authentication.
- [ ] Dashboard routes are protected by authorization middleware or gates.
- [ ] Dashboard access is restricted to trusted users only.
- [ ] Dashboard routes are not publicly accessible.

Recommended configuration:

php 'dashboard' => [     'enabled' => env('LARAWEBHOOK_DASHBOARD_ENABLED', false),     'path' => env('LARAWEBHOOK_DASHBOARD_PATH', 'larawebhook/dashboard'),     'middleware' => ['web', 'auth'], ],

For stricter access control:

php 'dashboard' => [     'enabled' => env('LARAWEBHOOK_DASHBOARD_ENABLED', false),     'path' => env('LARAWEBHOOK_DASHBOARD_PATH', 'larawebhook/dashboard'),     'middleware' => ['web', 'auth', 'can:manageLaraWebhook'], ],

### API

- [ ] API routes are disabled if they are not needed in production.
- [ ] If enabled, API routes are protected by authentication.
- [ ] API routes are protected by authorization middleware or gates.
- [ ] Replay endpoints are not publicly accessible.
- [ ] API tokens or credentials are scoped and rotated according to your internal policy.

Recommended configuration:

php 'api' => [     'enabled' => env('LARAWEBHOOK_API_ENABLED', false),     'path' => env('LARAWEBHOOK_API_PATH', 'api/larawebhook'),     'middleware' => ['api', 'auth:sanctum'], ],

For stricter access control:

php 'api' => [     'enabled' => env('LARAWEBHOOK_API_ENABLED', false),     'path' => env('LARAWEBHOOK_API_PATH', 'api/larawebhook'),     'middleware' => ['api', 'auth:sanctum', 'can:manageLaraWebhook'], ],

## Payload storage

- [ ] A payload storage mode has been explicitly chosen.
- [ ] The selected mode matches your debugging needs and privacy requirements.
- [ ] Full payload storage is enabled only when strictly necessary.
- [ ] Redaction rules have been reviewed against real provider payloads.
- [ ] Stored payloads are not exposed to unauthorized users.

Configuration:

php 'payload_storage' => [     'mode' => env('LARAWEBHOOK_PAYLOAD_STORAGE_MODE', 'redacted'), ],

Available modes:

| Mode | Description |
|---|---|
| none | Do not store webhook payloads. |
| redacted | Store payloads after sensitive fields have been masked. |
| full | Store full payloads. Use carefully in production. |

Recommended production default:

env LARAWEBHOOK_PAYLOAD_STORAGE_MODE=redacted

Use none when payload inspection and replay are not required.

Use full only when you have a clear operational need, strict access control and an appropriate retention policy.

## Redaction

- [ ] Sensitive fields are configured for redaction.
- [ ] Redaction rules include provider-specific sensitive fields.
- [ ] Redaction has been tested with representative webhook payloads.
- [ ] The team understands that redaction is not full anonymization.

Example configuration:

php 'redaction' => [     'fields' => [         'email',         'phone',         'address',         'token',         'secret',         'authorization',         'client_secret',         'password',         'api_key',         'access_token',         'refresh_token',     ],     'replacement' => '[REDACTED]', ],

## Retention

- [ ] Retention is enabled unless there is a deliberate reason to disable it.
- [ ] The retention period has been explicitly configured.
- [ ] The retention period matches your debugging, legal and internal data retention requirements.
- [ ] Long retention periods have been reviewed carefully, especially when payloads are stored.

Configuration:

php 'retention' => [     'enabled' => env('LARAWEBHOOK_RETENTION_ENABLED', true),     'days' => (int) env('LARAWEBHOOK_RETENTION_DAYS', 30), ],

Recommended production baseline:

env LARAWEBHOOK_RETENTION_ENABLED=true LARAWEBHOOK_RETENTION_DAYS=30

## Pruning

- [ ] The prune command has been tested manually.
- [ ] Dry-run mode has been used before enabling automatic pruning.
- [ ] Pruning is scheduled in Laravel.
- [ ] Pruning frequency matches the configured retention period.

Manual prune:

bash php artisan larawebhook:prune

Dry-run:

bash php artisan larawebhook:prune --older-than=30d --dry-run

Override retention duration:

bash php artisan larawebhook:prune --older-than=7d

Scheduler example:

php use Illuminate\Support\Facades\Schedule;  Schedule::command('larawebhook:prune')->daily();

## Replay

- [ ] Replay routes or actions are protected by authentication.
- [ ] Replay routes or actions are protected by authorization.
- [ ] Only trusted users can replay webhook events.
- [ ] Downstream handlers are idempotent.
- [ ] Replay behavior has been tested in a safe environment.
- [ ] The team understands that replay may trigger business side effects.
- [ ] Replay is not allowed when no payload is stored.
- [ ] Replaying redacted payloads is treated carefully because the stored payload may differ from the original provider payload.

Replay may trigger actions such as:

- updating an order;
- provisioning a resource;
- changing billing state;
- sending notifications;
- activating or disabling access.

Do not expose replay functionality publicly.

## Provider secrets

- [ ] Webhook secrets are configured through environment variables or a secret manager.
- [ ] Webhook secrets are not committed to the repository.
- [ ] Webhook secrets are different for each provider and environment.
- [ ] Production secrets are not reused in local or staging environments.
- [ ] Secrets are not logged.
- [ ] Secrets are not exposed in API responses, dashboard views or exception messages.
- [ ] Secret rotation is documented internally.

Example:

env STRIPE_WEBHOOK_SECRET=whsec_xxx GITHUB_WEBHOOK_SECRET=github_xxx

Example package configuration:

php 'services' => [     'stripe' => [         'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),     ],      'github' => [         'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),     ], ],

## Idempotency

- [ ] Duplicate webhook delivery has been considered.
- [ ] Provider event IDs are used when available.
- [ ] Payload hash fallback behavior is understood for providers without event IDs.
- [ ] Downstream application handlers are idempotent.
- [ ] Replayed events do not create duplicate business side effects.

LaraWebhook can help detect duplicate webhook events, but application-level handlers must still be safe to run more than once.

## Logging and errors

- [ ] Logs do not expose provider secrets.
- [ ] Logs do not expose raw payloads unless explicitly intended.
- [ ] Error messages returned to clients do not leak internal details.
- [ ] Monitoring is configured for failed webhook processing.
- [ ] Alerting is configured for repeated failures if required.

## Final production readiness check

Before enabling LaraWebhook in production, confirm:

- [ ] Dashboard is disabled or protected.
- [ ] API is disabled or protected.
- [ ] Replay is restricted to trusted users.
- [ ] Payload storage mode is explicitly chosen.
- [ ] Redaction rules are configured.
- [ ] Retention is configured.
- [ ] Pruning is scheduled.
- [ ] Provider secrets are configured securely.
- [ ] Downstream handlers are idempotent.
- [ ] Security and privacy implications have been reviewed by the application owner.

LaraWebhook provides tools and safer defaults, but the application owner remains responsible for production configuration, access control, data retention, legal requirements and operational security.
