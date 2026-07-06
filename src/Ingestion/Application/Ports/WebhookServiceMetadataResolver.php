<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Ports;

use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

interface WebhookServiceMetadataResolver
{
    public function signatureHeader(WebhookService $service): string;

    public function timestampHeader(WebhookService $service): ?string;

    public function externalIdHeader(WebhookService $service): ?string;

    public function secret(WebhookService $service): ?string;
}
