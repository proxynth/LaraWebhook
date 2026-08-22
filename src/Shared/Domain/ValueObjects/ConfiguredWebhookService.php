<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Domain\ValueObjects;

use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

final readonly class ConfiguredWebhookService implements WebhookServiceIdentifier
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new WebhookException('Webhook service cannot be empty.');
        }

        return new self($value);
    }

    public static function resolve(string $value): WebhookServiceIdentifier
    {
        return WebhookService::tryFromString($value) ?? self::fromString($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
