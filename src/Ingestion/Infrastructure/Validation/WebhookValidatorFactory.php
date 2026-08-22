<?php

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Validation;

use Illuminate\Support\Facades\Config;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\ConfiguredWebhookService;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

class WebhookValidatorFactory
{
    /**
     * @var array<string, WebhookValidator>
     */
    private array $validators = [];

    public function __construct(
        private readonly WebhookSecretResolver $secretResolver,
        private readonly ?SignatureValidatorRegistry $signatureValidatorRegistry = null,
    ) {}

    /**
     * @throws WebhookException
     */
    public function forService(string|WebhookServiceIdentifier $service, ?string $secret = null): WebhookValidator
    {
        $service = $this->resolveService($service);

        $resolvedSecret = $secret ?? $this->secretResolver->resolve($service);

        if ($resolvedSecret === null || $resolvedSecret === '') {
            throw new WebhookException("No secret configured for service: {$service->value()}.");
        }

        if ($secret !== null) {
            return new WebhookValidator(
                secret: $resolvedSecret,
                tolerance: $this->toleranceForService($service->value()),
                registry: $this->signatureValidatorRegistry,
            );
        }

        if (! isset($this->validators[$service->value()])) {
            $this->validators[$service->value()] = new WebhookValidator(
                secret: $resolvedSecret,
                tolerance: $this->toleranceForService($service->value()),
                registry: $this->signatureValidatorRegistry,
            );
        }

        return $this->validators[$service->value()];
    }

    private function resolveService(string|WebhookServiceIdentifier $service): WebhookServiceIdentifier
    {
        if (is_string($service)) {
            $configured = config("larawebhook.services.{$service}");

            if (! WebhookService::isSupported($service)
                && (! is_array($configured) || empty($configured['webhook_secret']))) {
                throw new WebhookException("Webhook service '{$service}' is not supported");
            }

            return ConfiguredWebhookService::resolve($service);
        }

        return $service;
    }

    private function toleranceForService(string $service): int
    {
        if (Config::has("larawebhook.services.$service.tolerance")) {
            return (int) Config::get("larawebhook.services.$service.tolerance");
        }

        return 300;
    }
}
