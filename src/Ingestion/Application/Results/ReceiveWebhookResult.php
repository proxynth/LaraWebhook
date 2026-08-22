<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Results;

use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;
use Proxynth\Larawebhook\Shared\Application\Results\Result;
use Proxynth\Larawebhook\Shared\Domain\Events\DomainEvent;

final readonly class ReceiveWebhookResult implements Result
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_ALREADY_PROCESSED = 'already_processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ACCEPTED_FOR_RETRY = 'accepted_for_retry';

    public const STATUS_SECRET_NOT_CONFIGURED = 'secret_not_configured';

    private function __construct(
        public string $status,
        public ?WebhookLogData $log = null,
        public ?string $event = null,
        public ?string $externalId = null,
        public ?string $idempotencyKey = null,
        public ?string $errorMessage = null,
        public ?int $failureStatusCode = null,
        /** @var list<DomainEvent> */
        public array $events = [],
    ) {}

    public static function success(WebhookLogData $log, array $events = []): self
    {
        return new self(
            status: self::STATUS_SUCCESS,
            log: $log,
            event: $log->event,
            externalId: $log->externalId,
            idempotencyKey: $log->idempotencyKey,
            events: $events,
        );
    }

    public static function alreadyProcessed(?string $externalId, string $idempotencyKey, array $events = []): self
    {
        return new self(
            status: self::STATUS_ALREADY_PROCESSED,
            externalId: $externalId,
            idempotencyKey: $idempotencyKey,
            events: $events,
        );
    }

    public static function secretNotConfigured(string $service, array $events = []): self
    {
        return new self(
            status: self::STATUS_SECRET_NOT_CONFIGURED,
            errorMessage: "Webhook secret not configured for {$service}.",
            events: $events,
        );
    }

    public static function acceptedForRetry(
        WebhookLogData $log,
        string $event,
        ?string $externalId,
        ?string $idempotencyKey,
        array $events = [],
    ): self {
        return new self(
            status: self::STATUS_ACCEPTED_FOR_RETRY,
            log: $log,
            event: $event,
            externalId: $externalId,
            idempotencyKey: $idempotencyKey,
            errorMessage: 'Webhook validation failed, queued for retry',
            events: $events,
        );
    }

    public static function failed(WebhookLogData $log, int $statusCode, array $events = []): self
    {
        return new self(
            status: self::STATUS_FAILED,
            log: $log,
            event: $log->event,
            externalId: $log->externalId,
            idempotencyKey: $log->idempotencyKey,
            errorMessage: $log->errorMessage,
            failureStatusCode: $statusCode,
            events: $events,
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isAlreadyProcessed(): bool
    {
        return $this->status === self::STATUS_ALREADY_PROCESSED;
    }

    public function isSecretNotConfigured(): bool
    {
        return $this->status === self::STATUS_SECRET_NOT_CONFIGURED;
    }

    public function isAcceptedForRetry(): bool
    {
        return $this->status === self::STATUS_ACCEPTED_FOR_RETRY;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
