<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Data;

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Provider;
use Proxynth\Larawebhook\Processing\Domain\Entities\WebhookEvent;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\EventType;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\IdempotencyKey;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\WebhookStatus;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\ConfiguredWebhookService;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

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
        return WebhookEvent::fromHistory(
            provider: Provider::fromString($this->service),
            eventType: EventType::fromString($this->event),
            idempotencyKey: IdempotencyKey::optional($this->idempotencyKey),
            status: WebhookStatus::fromString($this->status),
        );
    }

    public function service(): WebhookServiceIdentifier
    {
        return ConfiguredWebhookService::resolve($this->service);
    }
}
