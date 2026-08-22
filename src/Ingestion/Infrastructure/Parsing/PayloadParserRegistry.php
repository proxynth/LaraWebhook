<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Parsing;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

final readonly class PayloadParserRegistry
{
    /** @param array<string, PayloadParserInterface> $parsers */
    public function __construct(private array $parsers = []) {}

    public function forService(WebhookServiceIdentifier $service): PayloadParserInterface
    {
        $parser = $this->parsers[$service->value()] ?? null;

        if ($parser === null) {
            throw new WebhookException("No payload parser registered for service: {$service->value()}.");
        }

        return $parser;
    }

    public function forServiceOrNull(WebhookServiceIdentifier $service): ?PayloadParserInterface
    {
        return $this->parsers[$service->value()] ?? null;
    }

    /** @return array<string, PayloadParserInterface> */
    public static function defaults(): array
    {
        return [
            'stripe' => new StripePayloadParser,
            'github' => new GithubPayloadParser,
            'slack' => new SlackPayloadParser,
            'shopify' => new ShopifyPayloadParser,
        ];
    }
}
