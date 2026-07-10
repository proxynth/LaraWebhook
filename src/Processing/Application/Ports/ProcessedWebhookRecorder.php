<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Ports;

use Proxynth\Larawebhook\Processing\Application\Results\ProcessedWebhookRecordResult;

interface ProcessedWebhookRecorder
{
    public function recordProcessed(
        string $service,
        string $idempotencyKey,
        ?string $externalId,
        ?string $event,
    ): ProcessedWebhookRecordResult;
}
