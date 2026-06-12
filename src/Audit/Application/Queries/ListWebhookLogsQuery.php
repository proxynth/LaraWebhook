<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Queries;

use Proxynth\Larawebhook\Shared\Application\Queries\Query;

final readonly class ListWebhookLogsQuery implements Query
{
    public function __construct(
        public ?string $service = null,
        public ?string $status = null,
        public ?string $event = null,
        public int $perPage = 25,
    ) {}
}
