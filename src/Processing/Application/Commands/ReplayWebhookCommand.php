<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Commands;

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Shared\Application\Commands\Command;

final readonly class ReplayWebhookCommand implements Command
{
    public function __construct(
        public WebhookLog $log,
        public string $signature,
    ) {}
}
