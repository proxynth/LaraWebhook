# Dashboard & REST API

LaraWebhook provides a modern dashboard and REST API for managing webhook logs.

## Dashboard

### Access

The dashboard is available at:

```
http://your-app.test/larawebhook/dashboard
```

### Features

- 📋 Paginated webhook logs table
- 🔍 Filter by service, status, and date
- 👁️ View detailed payload and error messages
- 🔄 Replay failed webhooks
- 📱 Fully responsive design

### Configuration

```php
// config/larawebhook.php
'dashboard' => [
    'enabled' => env('LARAWEBHOOK_DASHBOARD_ENABLED', true),
    'path' => env('LARAWEBHOOK_DASHBOARD_PATH', '/larawebhook/dashboard'),
    'middleware' => env('LARAWEBHOOK_DASHBOARD_MIDDLEWARE', 'web'),
],
```

### Disable Dashboard

```env
LARAWEBHOOK_DASHBOARD_ENABLED=false
```

### Change Path

```env
LARAWEBHOOK_DASHBOARD_PATH=/admin/webhooks
```

### Add Authentication

```env
LARAWEBHOOK_DASHBOARD_MIDDLEWARE=web,auth
```

## REST API

### Get Webhook Logs

```http
GET /api/larawebhook/logs
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `service` | string | Filter by service (stripe, github, etc.) |
| `status` | string | Filter by status (success, failed) |
| `date` | string | Filter by date (YYYY-MM-DD) |
| `per_page` | int | Results per page (default: 10) |
| `page` | int | Page number |

**Example:**

```bash
curl "https://your-app.test/api/larawebhook/logs?service=stripe&status=failed&per_page=25"
```

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "service": "stripe",
      "event": "payment_intent.succeeded",
      "status": "success",
      "payload": {...},
      "attempt": 0,
      "error_message": null,
      "created_at": "01/01/2024 10:30:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 50
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

### Replay a Webhook

```http
POST /api/larawebhook/logs/{id}/replay
```

Re-processes a webhook by validating and logging it again.

**Example:**

```bash
curl -X POST "https://your-app.test/api/larawebhook/logs/123/replay" \
  -H "Content-Type: application/json"
```

**Response:**

```json
{
  "success": true,
  "message": "Webhook replayed successfully!",
  "log": {
    "id": 124,
    "service": "stripe",
    "event": "payment_intent.succeeded",
    "status": "success",
    "attempt": 1
  }
}
```

## Using the API Programmatically

```php
use Illuminate\Support\Facades\Http;

// Get failed Stripe webhooks
$response = Http::get('/api/larawebhook/logs', [
    'service' => 'stripe',
    'status' => 'failed',
    'per_page' => 100,
]);

$logs = $response->json()['data'];

// Replay each failed webhook
foreach ($logs as $log) {
    Http::post("/api/larawebhook/logs/{$log['id']}/replay");
}
```

## Querying Logs Directly

```php
use Proxynth\Larawebhook\Models\WebhookLog;

// Get all logs
$logs = WebhookLog::all();

// Filter by service
$stripeLogs = WebhookLog::where('service', 'stripe')->get();

// Filter by status
$failedLogs = WebhookLog::where('status', 'failed')->get();

// Get recent failures
$recentFailures = WebhookLog::where('status', 'failed')
    ->where('created_at', '>', now()->subHour())
    ->get();

// Using scopes
$stripeLogs = WebhookLog::service('stripe')->get();
$failedLogs = WebhookLog::failed()->get();
$successLogs = WebhookLog::successful()->get();
```

## Log Entry Structure

```json
{
  "id": 1,
  "service": "stripe",
  "event": "payment_intent.succeeded",
  "status": "success",
  "payload": {
    "id": "evt_xxx",
    "type": "payment_intent.succeeded",
    "data": {...}
  },
  "attempt": 0,
  "error_message": null,
  "created_at": "2024-01-15 10:30:00",
  "updated_at": "2024-01-15 10:30:00"
}
```

| Field | Description |
|-------|-------------|
| `service` | Webhook provider (stripe, github, etc.) |
| `event` | Event type (payment_intent.succeeded, push, etc.) |
| `status` | success or failed |
| `payload` | Full webhook payload (JSON) |
| `attempt` | Retry attempt number (0 = first attempt) |
| `error_message` | Error message if failed |
