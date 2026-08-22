<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Ports;

use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

interface WebhookServiceMetadataResolver
{
    public function signatureHeader(WebhookServiceIdentifier $service): string;

    public function timestampHeader(WebhookServiceIdentifier $service): ?string;

    public function externalIdHeader(WebhookServiceIdentifier $service): ?string;
}
