<?php

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Validation;

use Illuminate\Support\Facades\Config;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Support\WebhookServiceMetadata;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

class WebhookValidatorFactory
{
    /**
     * @var array<string, WebhookValidator>
     */
    private array $validators = [];

    /**
     * @throws WebhookException
     */
    public function forService(string|WebhookService $service, ?string $secret = null): WebhookValidator
    {
        $service = $this->resolveService($service);

        $resolvedSecret = $secret ?? WebhookServiceMetadata::secret($service);

        if ($resolvedSecret === null || $resolvedSecret === '') {
            throw new WebhookException("No secret configured for service: {$service->value}");
        }

        if ($secret !== null) {
            return new WebhookValidator(
                secret: $resolvedSecret,
                tolerance: $this->toleranceForService($service->value),
            );
        }

        if (! isset($this->validators[$service->value])) {
            $this->validators[$service->value] = new WebhookValidator(
                secret: $resolvedSecret,
                tolerance: $this->toleranceForService($service->value),
            );
        }

        return $this->validators[$service->value];
    }

    private function resolveService(string|WebhookService $service): WebhookService
    {
        if (is_string($service)) {
            if (! WebhookService::isSupported($service)) {
                throw new WebhookException("Webhook service '$service' is not supported");
            }

            return WebhookService::fromString($service);
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
