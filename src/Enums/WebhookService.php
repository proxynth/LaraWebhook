<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Enums;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Parsers\GithubPayloadParser;
use Proxynth\Larawebhook\Parsers\StripePayloadParser;
use Proxynth\Larawebhook\Validators\GithubSignatureValidator;
use Proxynth\Larawebhook\Validators\StripeSignatureValidator;

/**
 * Supported webhook services.
 *
 * This enum centralizes the list of supported webhook providers
 * and their configuration (signature headers, parsers, validators, etc.).
 *
 * To add a new service:
 * 1. Add a new case to this enum
 * 2. Create a PayloadParser implementation in src/Parsers/
 * 3. Create a SignatureValidator implementation in src/Validators/
 * 4. Add the parser mapping in the parser() method
 * 5. Add the validator mapping in the signatureValidator() method
 * 6. Add signature header mapping in signatureHeader()
 */
enum WebhookService: string
{
    case Stripe = 'stripe';
    case Github = 'github';

    /**
     * Get the payload parser for this service.
     *
     * Each service has its own parser that knows how to extract
     * event types and metadata from the webhook payload.
     */
    public function parser(): PayloadParserInterface
    {
        return match ($this) {
            self::Stripe => new StripePayloadParser,
            self::Github => new GithubPayloadParser,
        };
    }

    /**
     * Get the signature validator for this service.
     *
     * Each service has its own validator that knows how to verify
     * the webhook signature according to the provider's format.
     */
    public function signatureValidator(): SignatureValidatorInterface
    {
        return match ($this) {
            self::Stripe => new StripeSignatureValidator,
            self::Github => new GithubSignatureValidator,
        };
    }

    /**
     * Get the signature header name for this service.
     */
    public function signatureHeader(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe-Signature',
            self::Github => 'X-Hub-Signature-256',
        };
    }

    /**
     * Get the config key for the webhook secret.
     */
    public function secretConfigKey(): string
    {
        return "larawebhook.services.{$this->value}.webhook_secret";
    }

    /**
     * Get the webhook secret from config.
     */
    public function secret(): ?string
    {
        return config($this->secretConfigKey());
    }

    /**
     * Check if the service is supported.
     */
    public static function isSupported(string $service): bool
    {
        return self::tryFrom($service) !== null;
    }

    /**
     * Get a service from string or throw an exception.
     *
     * @throws \ValueError
     */
    public static function fromString(string $service): self
    {
        return self::from($service);
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
