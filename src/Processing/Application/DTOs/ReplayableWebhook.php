<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\DTOs;

use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Provider;
use Proxynth\Larawebhook\Processing\Domain\Entities\WebhookEvent;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\EventType;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\IdempotencyKey;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\WebhookStatus;

final readonly class ReplayableWebhook
{
    public function __construct(
        public int|string $id,
        public string $service,
        public string $event,
        public ?array $payload,
        public int $attempt,
        public ?string $externalId,
        public ?string $idempotencyKey,
        public string $status,
    ) {}

    public function toWebhookEvent(): WebhookEvent
    {
        return WebhookEvent::replayable(
            provider: Provider::fromString($this->service),
            eventType: EventType::fromString($this->event),
            idempotencyKey: IdempotencyKey::optional($this->idempotencyKey),
            status: WebhookStatus::fromString($this->status),
        );
    }

    public function service(): WebhookService
    {
        return WebhookService::fromString($this->service);
    }
}
