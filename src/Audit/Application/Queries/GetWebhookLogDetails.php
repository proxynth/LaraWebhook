<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Queries;

use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final readonly class GetWebhookLogDetails
{
    public function handle(GetWebhookLogDetailsQuery $query): WebhookLogDetails
    {
        $log = WebhookLog::query()->findOrFail($query->webhookLogId);

        return WebhookLogDetails::fromModel($log);
    }
}
