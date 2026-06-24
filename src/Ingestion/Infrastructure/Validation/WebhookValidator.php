<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Validation;

use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

class WebhookValidator
{
    public function __construct(
        private readonly string $secret,
        private readonly int $tolerance = 300,
    ) {}

    /**
     * Validates a webhook signature using the service's validator.
     *
     * @param  string  $payload  Raw webhook content
     * @param  Signature  $signature  Signature provided by the service
     * @param  string|WebhookService  $service  Service name or enum
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public function validate(string $payload, Signature $signature, string|WebhookService $service): bool
    {
        $webhookService = $service instanceof WebhookService
            ? $service
            : WebhookService::tryFromString($service);

        if ($webhookService === null) {
            throw new WebhookException("Unsupported service: {$service}");
        }

        return match ($webhookService) {
            WebhookService::Stripe => (new StripeSignatureValidator)->validate(
                $payload,
                $signature,
                $this->secret,
                $this->tolerance
            ),
            WebhookService::Github => (new GithubSignatureValidator)->validate(
                $payload,
                $signature,
                $this->secret,
                $this->tolerance
            ),
            WebhookService::Slack => (new SlackSignatureValidator)->validate(
                $payload,
                $signature,
                $this->secret,
                $this->tolerance
            ),
            WebhookService::Shopify => (new ShopifySignatureValidator)->validate(
                $payload,
                $signature,
                $this->secret,
                $this->tolerance
            ),
        };
    }
}
