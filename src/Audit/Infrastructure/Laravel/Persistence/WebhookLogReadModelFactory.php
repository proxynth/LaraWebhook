<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence;

use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final class WebhookLogReadModelFactory
{
    public function summary(WebhookLog $log): WebhookLogSummary
    {
        return new WebhookLogSummary(
            id: $log->getKey(),
            service: $log->service,
            event: $log->event,
            status: $log->status,
            attempt: $log->attempt,
            externalId: $log->external_id,
            idempotencyKey: $log->idempotency_key,
            createdAt: $log->created_at->toISOString() ?? '',
        );
    }

    public function details(WebhookLog $log): WebhookLogDetails
    {
        return new WebhookLogDetails(
            id: $log->getKey(),
            service: $log->service,
            event: $log->event,
            status: $log->status,
            payload: $log->payload,
            errorMessage: $log->error_message,
            attempt: $log->attempt,
            externalId: $log->external_id,
            idempotencyKey: $log->idempotency_key,
            createdAt: $log->created_at->toISOString() ?? '',
            updatedAt: $log->updated_at->toISOString() ?? '',
        );
    }

    public function failureDetails(WebhookLog $log): WebhookFailureDetails
    {
        return new WebhookFailureDetails(
            id: $log->getKey(),
            service: $log->service,
            event: $log->event,
            status: $log->status,
            errorMessage: $log->error_message ?? 'Unknown webhook failure.',
            attempt: $log->attempt,
            externalId: $log->external_id,
            idempotencyKey: $log->idempotency_key,
            createdAt: $log->created_at->toISOString() ?? '',
        );
    }
}
