<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\ReadModels;

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final readonly class WebhookLogDetails
{
    public function __construct(
        public int|string $id,
        public string $service,
        public ?string $event,
        public string $status,
        public ?array $payload,
        public ?string $errorMessage,
        public int $attempt,
        public ?string $externalId,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromModel(WebhookLog $log): self
    {
        return new self(
            id: $log->id,
            service: $log->service,
            event: $log->event,
            status: $log->status,
            payload: $log->payload,
            errorMessage: $log->error_message,
            attempt: $log->attempt,
            externalId: $log->external_id,
            createdAt: $log->created_at->toISOString() ?? '',
            updatedAt: $log->updated_at->toISOString() ?? '',
        );
    }
}
