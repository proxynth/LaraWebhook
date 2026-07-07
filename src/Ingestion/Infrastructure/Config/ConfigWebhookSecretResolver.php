<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Config;

use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

final class ConfigWebhookSecretResolver implements WebhookSecretResolver
{
    public function resolve(WebhookService $service): ?string
    {
        $secret = config("larawebhook.services.{$service->value}.webhook_secret");

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
