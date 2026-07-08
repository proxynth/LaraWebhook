<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Deduplication;

use Proxynth\Larawebhook\Processing\Application\Ports\WebhookDuplicateDetector;
use Proxynth\Larawebhook\Processing\Infrastructure\Persistence\Models\ProcessedWebhookEvent;

final readonly class EloquentWebhookDuplicateDetector implements WebhookDuplicateDetector
{
    public function alreadyProcessed(string $service, string $idempotencyKey): bool
    {
        return ProcessedWebhookEvent::query()
            ->where('service', $service)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }
}
