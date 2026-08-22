<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Validation\SignatureValidatorRegistry;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\ConfiguredWebhookService;

it('allows a custom validator to replace a built-in validator', function () {
    $custom = new class implements SignatureValidatorInterface
    {
        public function validate(string $payload, Signature $signature, string $secret, int $tolerance = 300): bool
        {
            return $secret === 'custom-secret';
        }

        public function serviceName(): string
        {
            return 'stripe';
        }
    };

    $registry = new SignatureValidatorRegistry([
        'stripe' => $custom,
    ]);

    expect($registry->forService(WebhookService::Stripe))->toBe($custom);
});

it('resolves a configured service outside the built-in enum', function () {
    $service = ConfiguredWebhookService::resolve('twilio');

    expect($service->value())->toBe('twilio')
        ->and($service)->not->toBeInstanceOf(WebhookService::class);
});
