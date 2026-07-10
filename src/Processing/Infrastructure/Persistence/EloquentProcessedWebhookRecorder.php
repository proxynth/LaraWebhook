<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Persistence;

use Illuminate\Database\QueryException;
use Proxynth\Larawebhook\Processing\Application\Ports\ProcessedWebhookRecorder;
use Proxynth\Larawebhook\Processing\Application\Results\ProcessedWebhookRecordResult;
use Proxynth\Larawebhook\Processing\Infrastructure\Persistence\Models\ProcessedWebhookEvent;

final readonly class EloquentProcessedWebhookRecorder implements ProcessedWebhookRecorder
{
    public function recordProcessed(
        string $service,
        string $idempotencyKey,
        ?string $externalId,
        ?string $event,
    ): ProcessedWebhookRecordResult {
        try {
            ProcessedWebhookEvent::query()->create([
                'service' => $service,
                'idempotency_key' => $idempotencyKey,
                'external_id' => $externalId,
                'event' => $event,
                'processed_at' => now(),
            ]);

            return ProcessedWebhookRecordResult::recorded();
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return ProcessedWebhookRecordResult::alreadyRecorded();
            }

            throw $exception;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;

        return $sqlState === '23000'
            || $sqlState === '23505'
            || $driverCode === 1062
            || $driverCode === 19;
    }
}
