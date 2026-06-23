<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

/**
 * @mixin WebhookLog
 *
 * @property int $id
 * @property string $service
 * @property string $event
 * @property string $status
 * @property array $payload
 * @property string|null $error_message
 * @property int $attempt
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return match (true) {
            $this->resource instanceof WebhookLogSummary => $this->summary($this->resource),
            $this->resource instanceof WebhookLogDetails => $this->details($this->resource),
            $this->resource instanceof WebhookFailureDetails => $this->failureDetails($this->resource),
            $this->resource instanceof WebhookLog => $this->details(WebhookLogDetails::fromModel($this->resource)),
            default => throw new InvalidArgumentException(sprintf(
                'WebhookLogResource expects [%s], [%s], [%s] or [%s], [%s] given.',
                WebhookLogSummary::class,
                WebhookLogDetails::class,
                WebhookFailureDetails::class,
                WebhookLog::class,
                get_debug_type($this->resource),
            )),
        };
    }

    private function summary(WebhookLogSummary $log): array
    {
        return [
            'id' => $log->id,
            'service' => $log->service,
            'event' => $log->event,
            'status' => $log->status,
            'attempt' => $log->attempt,
            'external_id' => $log->externalId,
            'idempotency_key' => $log->idempotencyKey,
            'created_at' => $log->createdAt,
        ];

    }

    private function details(WebhookLogDetails $log): array
    {
        return [
            'id' => $log->id,
            'service' => $log->service,
            'event' => $log->event,
            'status' => $log->status,
            'payload' => $log->payload,
            'error_message' => $log->errorMessage,
            'attempt' => $log->attempt,
            'external_id' => $log->externalId,
            'idempotency_key' => $log->idempotencyKey,
            'created_at' => $log->createdAt,
            'updated_at' => $log->updatedAt,
        ];
    }

    private function failureDetails(WebhookFailureDetails $log): array
    {
        return [
            'id' => $log->id,
            'service' => $log->service,
            'event' => $log->event,
            'status' => $log->status,
            'error_message' => $log->errorMessage,
            'attempt' => $log->attempt,
            'external_id' => $log->externalId,
            'idempotency_key' => $log->idempotencyKey,
            'created_at' => $log->createdAt,
        ];
    }
}
