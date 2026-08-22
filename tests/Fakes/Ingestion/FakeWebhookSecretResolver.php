<?php

namespace Proxynth\Larawebhook\Tests\Fakes\Ingestion;

use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

final readonly class FakeWebhookSecretResolver implements WebhookSecretResolver
{
    public function __construct(
        private array $secrets = [],
    ) {}

    public function resolve(WebhookServiceIdentifier $service): ?string
    {
        return $this->secrets[$service->value] ?? null;
    }
}
