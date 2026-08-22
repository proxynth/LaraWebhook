<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Support;

use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookServiceMetadataResolver;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

final class LaravelWebhookServiceMetadataResolver implements WebhookServiceMetadataResolver
{
    public function signatureHeader(WebhookServiceIdentifier $service): string
    {
        if ($service instanceof WebhookService) {
            return match ($service) {
                WebhookService::Stripe => 'Stripe-Signature',
                WebhookService::Github => 'X-Hub-Signature-256',
                WebhookService::Slack => 'X-Slack-Signature',
                WebhookService::Shopify => 'X-Shopify-Hmac-Sha256',
            };
        }

        return (string) config("larawebhook.services.{$service->value()}.signature_header", 'X-Webhook-Signature');
    }

    public function timestampHeader(WebhookServiceIdentifier $service): ?string
    {
        if ($service instanceof WebhookService) {
            return match ($service) {
                WebhookService::Slack => 'X-Slack-Request-Timestamp',
                default => null,
            };
        }

        return config("larawebhook.services.{$service->value()}.timestamp_header");
    }

    public function externalIdHeader(WebhookServiceIdentifier $service): ?string
    {
        if ($service instanceof WebhookService) {
            return match ($service) {
                WebhookService::Github => 'X-GitHub-Delivery',
                WebhookService::Shopify => 'X-Shopify-Webhook-Id',
                default => null,
            };
        }

        return config("larawebhook.services.{$service->value()}.external_id_header");
    }
}
