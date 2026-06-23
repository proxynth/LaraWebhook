<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Queries;

use Illuminate\Pagination\LengthAwarePaginator;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogReadRepository;

final readonly class ListWebhookLogs
{
    public function __construct(
        private WebhookLogReadRepository $readRepository,
    ) {}

    public function handle(ListWebhookLogsQuery $query): LengthAwarePaginator
    {
        return $this->readRepository->paginateSummaries($query);
    }
}
