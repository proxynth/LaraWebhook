<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Ingestion\Infrastructure\Config\ConfigWebhookSecretResolver;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

it('resolves webhook secret from config', function () {
    config()->set('larawebhook.services.github.webhook_secret', 'github_secret');

    $resolver = new ConfigWebhookSecretResolver;

    expect($resolver->resolve(WebhookService::Github))->toBe('github_secret');
});

it('returns null when webhook secret is missing', function () {
    config()->set('larawebhook.services.github.webhook_secret', null);

    $resolver = new ConfigWebhookSecretResolver;

    expect($resolver->resolve(WebhookService::Github))->toBeNull();
});

it('returns null when webhook secret is empty', function () {
    config()->set('larawebhook.services.github.webhook_secret', '');

    $resolver = new ConfigWebhookSecretResolver;

    expect($resolver->resolve(WebhookService::Github))->toBeNull();
});
