<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Deduplication;

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Processing\Application\Ports\WebhookDuplicateDetector;

final readonly class EloquentWebhookDuplicateDetector implements WebhookDuplicateDetector
{
    public function alreadyProcessed(string $service, string $idempotencyKey): bool
    {
        return WebhookLog::existsForIdempotencyKey($service, $idempotencyKey);
    }
}
