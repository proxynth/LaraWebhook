<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Queries;

use Proxynth\Larawebhook\Shared\Application\Queries\Query;

final readonly class GetWebhookFailureDetailsQuery implements Query
{
    public function __construct(
        public int|string $webhookLogId,
    ) {}
}
