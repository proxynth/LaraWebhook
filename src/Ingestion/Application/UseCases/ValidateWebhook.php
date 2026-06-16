<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\UseCases;

use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Results\ValidateWebhookResult;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Validation\WebhookValidatorFactory;

final readonly class ValidateWebhook
{
    public function __construct(
        private WebhookValidatorFactory $validatorFactory,
    ) {}

    public function handle(ValidateWebhookCommand $command): ValidateWebhookResult
    {
        $serviceName = $command->service->value;
        $decodedPayload = $command->payload->decoded();

        try {
            $this->validatorFactory
                ->forService($command->service, $command->secret)
                ->validate(
                    payload: $command->payload->value(),
                    signature: $command->signature,
                    service: $command->service,
                );

            return ValidateWebhookResult::valid(
                service: $serviceName,
                event: $command->event,
                externalId: $command->externalId,
                payload: $decodedPayload,
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
