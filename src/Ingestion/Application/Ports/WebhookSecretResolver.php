<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Ports;

use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

interface WebhookSecretResolver
{
    public function resolve(WebhookService $service): ?string;
}
