<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Logging;

use Carbon\CarbonInterface;
use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final class WebhookLogDataFactory
{
    public static function fromModel(WebhookLog $log): WebhookLogData
    {
        $createdAt = $log->getAttribute('created_at');
        $updatedAt = $log->getAttribute('updated_at');

        return new WebhookLogData(
            id: $log->getKey() ?? 0,
            service: $log->service,
            event: $log->event,
            status: $log->status,
            payload: $log->payload,
            errorMessage: $log->error_message,
            attempt: $log->attempt,
            externalId: $log->external_id,
            idempotencyKey: $log->idempotency_key,
            createdAt: $createdAt instanceof CarbonInterface ? $createdAt->toISOString() : '',
            updatedAt: $updatedAt instanceof CarbonInterface ? $updatedAt->toISOString() : '',
        );
    }
}
