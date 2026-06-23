<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Ports;

use Illuminate\Pagination\LengthAwarePaginator;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;

interface WebhookLogReadRepository
{
    public function paginateSummaries(ListWebhookLogsQuery $query): LengthAwarePaginator;

    public function findDetails(int|string $id): WebhookLogDetails;

    public function findFailureDetails(int|string $id): WebhookFailureDetails;
}
