<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Commands;

use Proxynth\Larawebhook\Shared\Application\Commands\Command;

final readonly class RecordWebhookLogCommand implements Command
{
    public function __construct(
        public string $service,
        public string $event,
        public bool $valid,
        public array $payload,
        public int $attempt = 0,
        public ?string $externalId = null,
        public ?string $errorMessage = null,
    ) {}
}
