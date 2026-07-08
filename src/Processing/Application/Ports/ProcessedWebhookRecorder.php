<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Ports;

interface ProcessedWebhookRecorder
{
    public function recordProcessed(
        string $service,
        string $idempotencyKey,
        ?string $externalId,
        ?string $event,
    ): void;
}
