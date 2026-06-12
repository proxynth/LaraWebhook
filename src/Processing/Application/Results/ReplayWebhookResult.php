<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Results;

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Shared\Application\Results\Result;

final readonly class ReplayWebhookResult implements Result
{
    public function __construct(
        public WebhookLog $log,
    ) {}
}
