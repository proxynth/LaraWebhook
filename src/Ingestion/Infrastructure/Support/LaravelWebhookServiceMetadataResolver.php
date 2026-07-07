<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Support;

use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookServiceMetadataResolver;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

final class LaravelWebhookServiceMetadataResolver implements WebhookServiceMetadataResolver
{
    public function signatureHeader(WebhookService $service): string
    {
        return match ($service) {
            WebhookService::Stripe => 'Stripe-Signature',
            WebhookService::Github => 'X-Hub-Signature-256',
            WebhookService::Slack => 'X-Slack-Signature',
            WebhookService::Shopify => 'X-Shopify-Hmac-Sha256',
        };
    }

    public function timestampHeader(WebhookService $service): ?string
    {
        return match ($service) {
            WebhookService::Slack => 'X-Slack-Request-Timestamp',
            default => null,
        };
    }

    public function externalIdHeader(WebhookService $service): ?string
    {
        return match ($service) {
            WebhookService::Github => 'X-GitHub-Delivery',
            WebhookService::Shopify => 'X-Shopify-Webhook-Id',
            default => null,
        };
    }
}
