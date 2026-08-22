<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\ReadModels;

final readonly class WebhookLogSummary
{
    public function __construct(
        public int|string $id,
        public string $service,
        public ?string $event,
        public string $status,
        public int $attempt,
        public ?string $externalId,
        public ?string $idempotencyKey,
        public string $createdAt,
    ) {}

}
