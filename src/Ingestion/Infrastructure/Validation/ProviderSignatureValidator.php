<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Validation;

use Proxynth\Larawebhook\Ingestion\Application\Ports\SignatureValidator;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

final readonly class ProviderSignatureValidator implements SignatureValidator
{
    public function __construct(
        private WebhookValidatorFactory $validatorFactory,
    ) {}

    public function validate(
        WebhookService $service,
        RawPayload $payload,
        Signature $signature,
        string $secret,
    ): bool {
        return $this->validatorFactory
            ->forService($service, $secret)
            ->validate(
                payload: $payload->value(),
                signature: $signature,
                service: $service,
            );
    }
}
