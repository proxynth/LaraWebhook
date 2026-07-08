<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

it('allows multiple webhook logs with same service and idempotency key', function () {
    WebhookLog::factory()->create([
        'service' => 'github',
        'event' => 'opened',
        'status' => 'success',
        'external_id' => 'delivery_123',
        'idempotency_key' => 'delivery_123',
    ]);

    WebhookLog::factory()->create([
        'service' => 'github',
        'event' => 'opened',
        'status' => 'failed',
        'external_id' => 'delivery_123',
        'idempotency_key' => 'delivery_123',
        'error_message' => 'Retry failed',
    ]);

    expect(WebhookLog::query()->count())->toBe(2);
});
