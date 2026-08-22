<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Queries;

use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogPage;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogReadRepository;

final readonly class ListWebhookLogs
{
    public function __construct(
        private WebhookLogReadRepository $readRepository,
    ) {}

    public function handle(ListWebhookLogsQuery $query): WebhookLogPage
    {
        return $this->readRepository->paginateSummaries($query);
    }
}
