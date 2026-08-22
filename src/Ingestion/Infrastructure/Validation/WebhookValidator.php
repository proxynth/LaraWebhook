<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Validation;

use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\ConfiguredWebhookService;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

class WebhookValidator
{
    public function __construct(
        private readonly string $secret,
        private readonly int $tolerance = 300,
        private readonly ?SignatureValidatorRegistry $registry = null,
    ) {}

    /**
     * Validates a webhook signature using the service's validator.
     *
     * @param  string  $payload  Raw webhook content
     * @param  Signature  $signature  Signature provided by the service
     * @param  string|WebhookServiceIdentifier  $service  Service name or identifier
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public function validate(string $payload, Signature $signature, string|WebhookServiceIdentifier $service): bool
    {
        if (is_string($service) && ! array_key_exists($service, (array) config('larawebhook.services', []))) {
            throw new WebhookException("Unsupported service: {$service}");
        }

        $webhookService = is_string($service) ? ConfiguredWebhookService::resolve($service) : $service;

        $registry = $this->registry ?? new SignatureValidatorRegistry(SignatureValidatorRegistry::defaults());

        return $registry->forService($webhookService)->validate(
            $payload,
            $signature,
            $this->secret,
            $this->tolerance,
        );
    }
}
