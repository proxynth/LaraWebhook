<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Parsing;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookPayloadParserResolver;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

final readonly class ProviderPayloadParserResolver implements WebhookPayloadParserResolver
{
    public function __construct(
        private PayloadParserRegistry $registry = new PayloadParserRegistry([]),
    ) {}

    public function forService(WebhookServiceIdentifier $service): PayloadParserInterface
    {
        $registry = $this->registry;

        if ($registry->forServiceOrNull($service) === null) {
            $registry = new PayloadParserRegistry(PayloadParserRegistry::defaults());
        }

        return $registry->forService($service);
    }
}
