<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\Exceptions;

use DomainException;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\IdempotencyKey;

final class DuplicateWebhookEvent extends DomainException
{
    public static function withIdempotencyKey(IdempotencyKey $idempotencyKey): self
    {
        return new self("Webhook event with idempotency key [{$idempotencyKey->value()}] has already been processed.");
    }
}
