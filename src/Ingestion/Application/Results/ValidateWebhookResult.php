<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Results;

use Proxynth\Larawebhook\Shared\Application\Results\Result;

final readonly class ValidateWebhookResult implements Result
{
    public function __construct(
        public bool $valid,
        public string $service,
        public string $event,
        public ?string $externalId,
        public array $payload,
        public ?string $errorMessage = null,
    ) {}

    public static function valid(
        string $service,
        string $event,
        ?string $externalId,
        array $payload,
    ): self {
        return new self(
            valid: true,
            service: $service,
            event: $event,
            externalId: $externalId,
            payload: $payload,
        );
    }

    public static function invalid(
        string $service,
        string $event,
        ?string $externalId,
        array $payload,
        string $errorMessage,
    ): self {
        return new self(
            valid: false,
            service: $service,
            event: $event,
            externalId: $externalId,
            payload: $payload,
            errorMessage: $errorMessage,
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function isInvalid(): bool
    {
        return ! $this->valid;
    }
}
