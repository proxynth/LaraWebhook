<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogReadModel;
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
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $log = $this->toReadModel();

        return [
            'id' => $log->id,
            'service' => $log->service,
            'event' => $log->event,
            'status' => $log->status,
            'payload' => $log->payload,
            'error_message' => $log->errorMessage,
            'attempt' => $log->attempt,
            'created_at' => $log->createdAt?->toISOString(),
            'updated_at' => $log->updatedAt?->toISOString(),
        ];
    }

    private function toReadModel(): WebhookLogReadModel
    {
        if ($this->resource instanceof WebhookLogReadModel) {
            return $this->resource;
        }

        if ($this->resource instanceof WebhookLog) {
            return WebhookLogReadModel::fromModel($this->resource);
        }

        throw new \InvalidArgumentException(sprintf(
            'WebhookLogResource expects [%s] or [%s], [%s] given.',
            WebhookLogReadModel::class,
            WebhookLog::class,
            get_debug_type($this->resource)
        ));
    }
}
