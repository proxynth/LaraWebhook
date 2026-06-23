<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\ReadModels;

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final readonly class WebhookFailureDetails
{
    public function __construct(
        public int|string $id,
        public string $service,
        public ?string $event,
        public string $status,
        public string $errorMessage,
        public int $attempt,
        public ?string $externalId,
        public ?string $idempotencyKey,
        public string $createdAt,
    ) {}

    public static function fromModel(WebhookLog $log): self
    {
        return new self(
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
