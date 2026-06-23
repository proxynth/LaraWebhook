<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Commands;

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Application\Commands\Command;

final readonly class RetryWebhookCommand implements Command
{
    public function __construct(
        public string $payload,
        public Signature $signature,
        public string $service,
        public string $event,
        public string $secret,
        public int $attempt = 0,
        public ?string $externalId = null,
        public ?string $idempotencyKey = null,
    ) {}
}
