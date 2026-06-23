<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Domain\Entities;

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Provider;
use Proxynth\Larawebhook\Processing\Domain\Exceptions\DuplicateWebhookEvent;
use Proxynth\Larawebhook\Processing\Domain\Exceptions\InvalidWebhookState;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\EventType;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\IdempotencyKey;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\WebhookStatus;

final class WebhookEvent
{
    private ?string $failureReason = null;

    private function __construct(
        private readonly Provider $provider,
        private readonly EventType $eventType,
        private readonly ?IdempotencyKey $idempotencyKey,
        private WebhookStatus $status,
        private readonly bool $valid,
    ) {}

    public static function received(
        Provider $provider,
        EventType $eventType,
        ?IdempotencyKey $idempotencyKey,
        bool $valid = true,
        bool $alreadyProcessed = false,
    ): self {
        if ($alreadyProcessed && $idempotencyKey !== null) {
            throw DuplicateWebhookEvent::withIdempotencyKey($idempotencyKey);
        }

        return new self(
            provider: $provider,
            eventType: $eventType,
            idempotencyKey: $idempotencyKey,
            status: WebhookStatus::received(),
            valid: $valid,
        );
    }

    public function markValidated(): void
    {
        $this->ensureNotTerminal();

        if (! $this->valid) {
            throw InvalidWebhookState::cannotProcessInvalidEvent();
        }

        if (! $this->status->isReceived()) {
            throw InvalidWebhookState::invalidTransition($this->status, WebhookStatus::VALIDATED);
        }

        $this->status = WebhookStatus::validated();
    }

    public function markProcessing(): void
    {
        $this->ensureNotTerminal();

        if (! $this->valid) {
            throw InvalidWebhookState::cannotProcessInvalidEvent();
        }

        if (! $this->status->isValidated()) {
            throw InvalidWebhookState::invalidTransition($this->status, WebhookStatus::PROCESSING);
        }

        $this->status = WebhookStatus::processing();
    }

    public function markProcessed(): void
    {
        $this->ensureNotTerminal();

        if (! $this->valid) {
            throw InvalidWebhookState::cannotProcessInvalidEvent();
        }

        if ($this->idempotencyKey === null) {
            throw InvalidWebhookState::cannotMarkProcessedWithoutIdempotencyKey();
        }

        if (! $this->status->isProcessing()) {
            throw InvalidWebhookState::invalidTransition($this->status, WebhookStatus::PROCESSED);
        }

        $this->status = WebhookStatus::processed();
    }

    public function markFailed(string $reason): void
    {
        $this->ensureNotTerminal();

        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Failure reason cannot be empty.');
        }

        if (! $this->status->isProcessing() && ! $this->status->isValidated() && ! $this->status->isReceived()) {
            throw InvalidWebhookState::invalidTransition($this->status, WebhookStatus::FAILED);
        }

        $this->failureReason = $reason;
        $this->status = WebhookStatus::failed();
    }

    public function replay(): self
    {
        if (! $this->status->canBeReplayed()) {
            throw InvalidWebhookState::cannotReplay($this->status);
        }

        return new self(
            provider: $this->provider,
            eventType: $this->eventType,
            idempotencyKey: $this->idempotencyKey,
            status: WebhookStatus::replayed(),
            valid: $this->valid,
        );
    }

    public static function replayable(
        Provider $provider,
        EventType $eventType,
        ?IdempotencyKey $idempotencyKey,
        WebhookStatus $status,
    ): self {
        return new self(
            provider: $provider,
            eventType: $eventType,
            idempotencyKey: $idempotencyKey,
            status: $status,
            valid: true,
        );
    }

    public function provider(): Provider
    {
        return $this->provider;
    }

    public function eventType(): EventType
    {
        return $this->eventType;
    }

    public function idempotencyKey(): ?IdempotencyKey
    {
        return $this->idempotencyKey;
    }

    public function status(): WebhookStatus
    {
        return $this->status;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    private function ensureNotTerminal(): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidWebhookState::terminalEventCannotMutate($this->status);
        }
    }
}
