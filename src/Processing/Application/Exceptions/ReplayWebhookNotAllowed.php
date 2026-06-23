<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Exceptions;

use RuntimeException;

final class ReplayWebhookNotAllowed extends RuntimeException
{
    public static function forWebhook(int|string $id, string $status): self
    {
        return new self("Webhook log [{$id}] cannot be replayed from status [{$status}].");
    }
}
