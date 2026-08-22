<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Data;

use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;

final readonly class WebhookLogPage
{
    /** @param list<WebhookLogSummary> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
        public int $lastPage,
        /** @var array{first:?string,last:?string,prev:?string,next:?string} */
        public array $links,
    ) {}
}
