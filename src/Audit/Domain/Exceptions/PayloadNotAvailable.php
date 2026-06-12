<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Domain\Exceptions;

use RuntimeException;

final class PayloadNotAvailable extends RuntimeException
{
    public static function forWebhookLog(int|string $logId): self
    {
        return new self("Payload is not available for webhook log [$logId]");
    }
}
