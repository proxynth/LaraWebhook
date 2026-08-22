<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Ports;

use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

interface WebhookSecretResolver
{
    public function resolve(WebhookServiceIdentifier $service): ?string;
}
