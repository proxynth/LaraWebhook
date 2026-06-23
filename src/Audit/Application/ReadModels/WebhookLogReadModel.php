<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\ReadModels;

use Carbon\CarbonInterface;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

class WebhookLogReadModel
{
    public function __construct(
        public int|string $id,
        public string $service,
        public ?string $event,
        public ?string $externalId,
        public ?string $idempotencyKey,
        public string $status,
        public ?array $payload,
        public int $attempt,
        public ?string $errorMessage,
        public ?CarbonInterface $createdAt,
        public ?CarbonInterface $updatedAt,
    ) {}

    public static function fromModel(WebhookLog $log): self
    {
        return new self(
            id: $log->id,
            service: $log->service,
            event: $log->event,
            externalId: $log->external_id,
            idempotencyKey: $log->idempotency_key,
            status: $log->status,
            payload: $log->payload,
            attempt: $log->attempt,
            errorMessage: $log->error_message,
            createdAt: $log->created_at,
            updatedAt: $log->updated_at,
        );
    }
}
