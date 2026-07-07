<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Ingestion\Infrastructure\Support\LaravelWebhookServiceMetadataResolver;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

beforeEach(function () {
    config([
        'larawebhook.services.stripe.webhook_secret' => 'stripe_secret',
        'larawebhook.services.github.webhook_secret' => 'github_secret',
        'larawebhook.services.slack.webhook_secret' => 'slack_secret',
        'larawebhook.services.shopify.webhook_secret' => 'shopify_secret',
    ]);
});

it('resolves provider-specific signature headers', function () {
    $resolver = new LaravelWebhookServiceMetadataResolver;

    expect($resolver->signatureHeader(WebhookService::Stripe))->toBe('Stripe-Signature')
        ->and($resolver->signatureHeader(WebhookService::Github))->toBe('X-Hub-Signature-256')
        ->and($resolver->signatureHeader(WebhookService::Slack))->toBe('X-Slack-Signature')
        ->and($resolver->signatureHeader(WebhookService::Shopify))->toBe('X-Shopify-Hmac-Sha256');
});

it('resolves provider-specific timestamp headers', function () {
    $resolver = new LaravelWebhookServiceMetadataResolver;

    expect($resolver->timestampHeader(WebhookService::Slack))->toBe('X-Slack-Request-Timestamp')
        ->and($resolver->timestampHeader(WebhookService::Stripe))->toBeNull()
        ->and($resolver->timestampHeader(WebhookService::Github))->toBeNull()
        ->and($resolver->timestampHeader(WebhookService::Shopify))->toBeNull();
});

it('resolves provider-specific external id headers', function () {
    $resolver = new LaravelWebhookServiceMetadataResolver;

    expect($resolver->externalIdHeader(WebhookService::Github))->toBe('X-GitHub-Delivery')
        ->and($resolver->externalIdHeader(WebhookService::Shopify))->toBe('X-Shopify-Webhook-Id')
        ->and($resolver->externalIdHeader(WebhookService::Stripe))->toBeNull()
        ->and($resolver->externalIdHeader(WebhookService::Slack))->toBeNull();
});
