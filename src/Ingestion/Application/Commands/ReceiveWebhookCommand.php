<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Commands;

use Proxynth\Larawebhook\Shared\Application\Commands\Command;

final readonly class ReceiveWebhookCommand implements Command
{
    public function __construct(
        public string $service,
        public string $rawPayload,
        public array $headers,
    ) {}
}
