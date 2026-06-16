<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Queries;

use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final readonly class GetWebhookFailureDetails
{
    public function handle(GetWebhookFailureDetailsQuery $query): WebhookFailureDetails
    {
        $log = WebhookLog::query()
            ->whereKey($query->webhookLogId)
            ->where('status', 'failed')
            ->firstOrFail();

        return WebhookFailureDetails::fromModel($log);
    }
}
