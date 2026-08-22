<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Data;

final readonly class WebhookLogData
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
        public ?string $idempotencyKey,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
