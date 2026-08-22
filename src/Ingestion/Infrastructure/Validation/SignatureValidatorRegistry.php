<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Validation;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

final readonly class SignatureValidatorRegistry
{
    /** @param array<string, SignatureValidatorInterface> $validators */
    public function __construct(private array $validators = []) {}

    public function forService(WebhookServiceIdentifier $service): SignatureValidatorInterface
    {
        $validator = $this->validators[$service->value()] ?? null;

        if ($validator === null) {
            throw new WebhookException("No signature validator registered for service: {$service->value()}.");
        }

        return $validator;
    }

    /** @return array<string, SignatureValidatorInterface> */
    public static function defaults(): array
    {
        return [
            'stripe' => new StripeSignatureValidator,
            'github' => new GithubSignatureValidator,
            'slack' => new SlackSignatureValidator,
            'shopify' => new ShopifySignatureValidator,
        ];
    }
}
