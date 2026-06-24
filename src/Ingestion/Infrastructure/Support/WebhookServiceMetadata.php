<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Support;

use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

final class WebhookServiceMetadata
{
    public static function signatureHeader(WebhookService $service): string
    {
        return match ($service) {
            WebhookService::Stripe => 'Stripe-Signature',
            WebhookService::Github => 'X-Hub-Signature-256',
            WebhookService::Slack => 'X-Slack-Signature',
            WebhookService::Shopify => 'X-Shopify-Hmac-Sha256',
        };
    }

    public static function timestampHeader(WebhookService $service): ?string
    {
        return match ($service) {
            WebhookService::Slack => 'X-Slack-Request-Timestamp',
            default => null,
        };
    }

    public static function externalIdHeader(WebhookService $service): ?string
    {
        return match ($service) {
            WebhookService::Github => 'X-GitHub-Delivery',
            WebhookService::Shopify => 'X-Shopify-Webhook-Id',
            default => null,
        };
    }

    public static function secretConfigKey(WebhookService $service): string
    {
        return "larawebhook.services.{$service->value}.webhook_secret";
    }

    public static function secret(WebhookService $service): ?string
    {
        return config(self::secretConfigKey($service));
    }
}
