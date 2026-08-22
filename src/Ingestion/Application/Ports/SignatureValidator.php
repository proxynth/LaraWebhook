<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Ports;

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

interface SignatureValidator
{
    public function validate(
        WebhookServiceIdentifier $service,
        RawPayload $payload,
        Signature $signature,
        string $secret,
    ): bool;
}
