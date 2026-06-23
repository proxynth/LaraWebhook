<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Ports;

use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;

interface SignatureValidator
{
    public function validate(
        WebhookService $service,
        RawPayload $payload,
        Signature $signature,
        string $secret,
    ): bool;
}
