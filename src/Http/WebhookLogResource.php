<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Proxynth\Larawebhook\Models\WebhookLog;

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
        return [
            'id' => $this->id,
            'service' => $this->service,
            'event' => $this->event,
            'status' => $this->status,
            'payload' => $this->payload,
            'error_message' => $this->error_message,
            'attempt' => $this->attempt,
            'created_at' => $this->created_at->format('d/m/Y H:i:s'),
            'updated_at' => $this->updated_at->format('d/m/Y H:i:s'),
        ];
    }
}
