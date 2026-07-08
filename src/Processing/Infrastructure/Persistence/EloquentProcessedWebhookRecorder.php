<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Persistence;

use Proxynth\Larawebhook\Processing\Application\Ports\ProcessedWebhookRecorder;
use Proxynth\Larawebhook\Processing\Infrastructure\Persistence\Models\ProcessedWebhookEvent;

class EloquentProcessedWebhookRecorder implements ProcessedWebhookRecorder
{
    public function recordProcessed(
        string $service,
        string $idempotencyKey,
        ?string $externalId,
        ?string $event,
    ): void {
        ProcessedWebhookEvent::query()->firstOrCreate([
            'service' => $service,
            'idempotency_key' => $idempotencyKey,
        ], [
            'external_id' => $externalId,
            'event' => $event,
            'processed_at' => now(),
        ]);
    }
}
