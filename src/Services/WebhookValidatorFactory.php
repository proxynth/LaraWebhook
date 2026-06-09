<?php

namespace Proxynth\Larawebhook\Services;

use Illuminate\Support\Facades\Config;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Exceptions\WebhookException;

class WebhookValidatorFactory
{
    /**
     * @var array<string, WebhookValidator>
     */
    private array $validators = [];

    /**
     * @throws WebhookException
     */
    public function forService(string|WebhookService $service): WebhookValidator
    {
        $service = $this->resolveService($service);

        if (! isset($this->validators[$service->value])) {
            if ($service->secret() === null) {
                throw new WebhookException("No secret configured for service: $service->value");
            }

            $this->validators[$service->value] = new WebhookValidator(
                secret: $service->secret(),
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
