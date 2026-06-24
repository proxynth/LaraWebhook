<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Parsing;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookPayloadParserResolver;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

final readonly class ProviderPayloadParserResolver implements WebhookPayloadParserResolver
{
    public function forService(WebhookService $service): PayloadParserInterface
    {
        return match ($service) {
            WebhookService::Stripe => new StripePayloadParser,
            WebhookService::Github => new GithubPayloadParser,
            WebhookService::Slack => new SlackPayloadParser,
            WebhookService::Shopify => new ShopifyPayloadParser,
        };
    }
}
