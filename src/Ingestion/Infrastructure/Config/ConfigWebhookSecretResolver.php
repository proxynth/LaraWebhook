<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Config;

use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

final class ConfigWebhookSecretResolver implements WebhookSecretResolver
{
    public function resolve(WebhookServiceIdentifier $service): ?string
    {
        $secret = config("larawebhook.services.{$service->value()}.webhook_secret");

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
