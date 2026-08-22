<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Domain\Enums;

use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

/**
 * Supported webhook services.
 *
 * This enum is intentionally pure: it only identifies supported providers
 * and offers string conversion helpers for application code.
 */
enum WebhookService: string implements WebhookServiceIdentifier
{
    case Stripe = 'stripe';
    case Github = 'github';
    case Slack = 'slack';
    case Shopify = 'shopify';

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Check if a service is supported.
     */
    public static function isSupported(string $service): bool
    {
        return self::tryFromString($service) !== null;
    }

    /**
     * Get a service from string or throw an exception.
     *
     * @throws WebhookException
     */
    public static function fromString(string $service): self
    {
        return self::tryFromString($service)
            ?? throw new WebhookException("Webhook service '{$service}' is not supported.");
    }

    /**
     * Try to get a service from string.
     */
    public static function tryFromString(string $service): ?self
    {
        return self::tryFrom($service);
    }

    /**
     * Get all supported service values as strings.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all services as an array for validation rules.
     *
     * @return array<string>
     */
    public static function validationRule(): array
    {
        return self::values();
    }
}
