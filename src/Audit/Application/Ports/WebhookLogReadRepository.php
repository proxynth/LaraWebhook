<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Ports;

use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogPage;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;

interface WebhookLogReadRepository
{
    public function paginateSummaries(ListWebhookLogsQuery $query): WebhookLogPage;

    public function findDetails(int|string $id): WebhookLogDetails;

    public function findFailureDetails(int|string $id): WebhookFailureDetails;
}
