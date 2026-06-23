<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Results;

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Shared\Application\Results\Result;

final readonly class ReceiveWebhookResult implements Result
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_ALREADY_PROCESSED = 'already_processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ACCEPTED_FOR_RETRY = 'accepted_for_retry';

    public const STATUS_SECRET_NOT_CONFIGURED = 'secret_not_configured';

    private function __construct(
        public string $status,
        public ?WebhookLog $log = null,
        public ?string $event = null,
        public ?string $externalId = null,
        public ?string $idempotencyKey = null,
        public ?string $errorMessage = null,
        public ?int $failureStatusCode = null,
        public ?string $secret = null,
    ) {}

    public static function success(WebhookLog $log): self
    {
        return new self(
            status: self::STATUS_SUCCESS,
            log: $log,
            event: $log->event,
            idempotencyKey: $log->idempotency_key,
        );
    }

    public static function alreadyProcessed(?string $externalId, string $idempotencyKey): self
    {
        return new self(
            status: self::STATUS_ALREADY_PROCESSED,
            externalId: $externalId,
            idempotencyKey: $idempotencyKey,
        );
    }

    public static function secretNotConfigured(string $service): self
    {
        return new self(
            status: self::STATUS_SECRET_NOT_CONFIGURED,
            errorMessage: "Webhook secret not configured for {$service}.",
        );
    }

    public static function acceptedForRetry(
        WebhookLog $log,
        string $event,
        string $secret,
        ?string $externalId,
        ?string $idempotencyKey,
    ): self {
        return new self(
            status: self::STATUS_ACCEPTED_FOR_RETRY,
            log: $log,
            event: $event,
            externalId: $externalId,
            idempotencyKey: $idempotencyKey,
            errorMessage: 'Webhook validation failed, queued for retry',
            secret: $secret,
        );
    }

    public static function failed(WebhookLog $log, int $statusCode): self
    {
        return new self(
            status: self::STATUS_FAILED,
            log: $log,
            event: $log->event,
            idempotencyKey: $log->external_id,
            errorMessage: $log->error_message,
            failureStatusCode: $statusCode,
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
