<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Persistence;

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Processing\Application\DTOs\ReplayableWebhook;
use Proxynth\Larawebhook\Processing\Application\Ports\ReplayableWebhookRepository;

final class EloquentReplayableWebhookRepository implements ReplayableWebhookRepository
{
    public function findReplayableById(int|string $id): ReplayableWebhook
    {
        $log = WebhookLog::query()->findOrFail($id);

        return new ReplayableWebhook(
            id: $log->getKey(),
            service: $log->service,
            event: $log->event,
            payload: $log->payload,
            attempt: $log->attempt,
            externalId: $log->external_id,
            idempotencyKey: $log->idempotency_key,
            status: $log->status,
        );
    }
}
