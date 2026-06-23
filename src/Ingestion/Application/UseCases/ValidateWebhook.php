<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\UseCases;

use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Ports\SignatureValidator;
use Proxynth\Larawebhook\Ingestion\Application\Results\ValidateWebhookResult;

final readonly class ValidateWebhook
{
    public function __construct(
        private SignatureValidator $signatureValidator,
    ) {}

    public function handle(ValidateWebhookCommand $command): ValidateWebhookResult
    {
        $serviceName = $command->service->value;
        $decodedPayload = $command->payload->decoded();

        try {
            $isValid = $this->signatureValidator->validate(
                service: $command->service,
                payload: $command->payload,
                signature: $command->signature,
                secret: $command->secret,
            );

            if ($isValid) {
                return ValidateWebhookResult::valid(
                    service: $serviceName,
                    event: $command->event,
                    externalId: $command->externalId,
                    payload: $decodedPayload,
                );
            }

            return ValidateWebhookResult::invalid(
                service: $serviceName,
                event: $command->event,
                externalId: $command->externalId,
                payload: $decodedPayload,
                errorMessage: 'Invalid webhook signature.',
            );
        } catch (WebhookException|InvalidSignatureException $exception) {
            return ValidateWebhookResult::invalid(
                service: $serviceName,
                event: $command->event,
                externalId: $command->externalId,
                payload: $decodedPayload,
                errorMessage: $exception->getMessage(),
            );
        }
    }
}
