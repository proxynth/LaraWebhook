<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\Exceptions;

use Proxynth\Larawebhook\Processing\Domain\ValueObjects\WebhookStatus;

final class InvalidWebhookState extends \DomainException
{
    public static function cannotProcessInvalidEvent(): self
    {
        return new self('Invalid webhook event cannot be processed.');
    }

    public static function terminalEventCannotMutate(WebhookStatus $status): self
    {
        return new self("Webhook event is terminal and cannot transition from [{$status->value()}].");

    }

    public static function cannotMarkProcessedWithoutIdempotencyKey(): self
    {
        return new self('Webhook event without idempotency key cannot be marked as processed.');
    }

    public static function cannotReplay(WebhookStatus $status): self
    {
        return new self("Webhook event with status [{$status->value()}] cannot be replayed.");
    }

    public static function invalidTransition(WebhookStatus $from, string $to): self
    {
        return new self("Cannot transition webhook event from [{$from->value()}] to [{$to}].");
    }
}
