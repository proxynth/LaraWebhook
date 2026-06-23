<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Ports;

interface WebhookDuplicateDetector
{
    public function alreadyProcessed(string $service, string $idempotencyKey): bool;
}
