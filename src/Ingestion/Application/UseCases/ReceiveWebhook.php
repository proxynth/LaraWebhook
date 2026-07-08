<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\UseCases;

use Exception;
use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ReceiveWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookPayloadParserResolver;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Ingestion\Application\Results\ReceiveWebhookResult;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookReceived;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookRejected;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookValidated;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Provider;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Processing\Application\Ports\IdempotencyResolver;
use Proxynth\Larawebhook\Processing\Application\Ports\ProcessedWebhookRecorder;
use Proxynth\Larawebhook\Processing\Application\Ports\RetryConfigurationResolver;
use Proxynth\Larawebhook\Processing\Application\Ports\WebhookDuplicateDetector;
use Proxynth\Larawebhook\Processing\Domain\Entities\WebhookEvent;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\DeliveryAttempt;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\EventType;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\IdempotencyKey;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;
use Symfony\Component\HttpFoundation\Response;

final readonly class ReceiveWebhook
{
    public function __construct(
        private IdempotencyResolver $idempotencyResolver,
        private WebhookDuplicateDetector $duplicateDetector,
        private WebhookPayloadParserResolver $payloadParserResolver,
        private WebhookSecretResolver $secretResolver,
        private RetryConfigurationResolver $retryConfigurationResolver,
        private ProcessedWebhookRecorder $processedWebhookRecorder,
        private ValidateWebhook $validateWebhook,
        private RecordWebhookLog $recordWebhookLog,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(ReceiveWebhookCommand $command): ReceiveWebhookResult
    {
        $provider = Provider::fromString($command->service->value);
        $rawPayload = RawPayload::fromString($command->payload);
        $decodedPayload = $rawPayload->decoded();

        $event = $this->extractEventType($decodedPayload, $command->service);

        $externalId = $this->extractExternalId(
            decodedPayload: $decodedPayload,
            service: $command->service,
            externalIdHeaderValue: $command->externalIdHeaderValue,
        );

        $idempotencyKeyValue = $this->idempotencyResolver->resolve(
            service: $provider->value(),
            payload: $decodedPayload,
            externalId: $externalId,
            event: $event,
        );

        $idempotencyKey = IdempotencyKey::optional($idempotencyKeyValue);

        if ($idempotencyKey !== null && $this->duplicateDetector->alreadyProcessed(
            $provider->value(),
            $idempotencyKey->value()
        )) {
            return ReceiveWebhookResult::alreadyProcessed(
                externalId: $externalId,
                idempotencyKey: $idempotencyKey->value(),
            );
        }

        $webhookEvent = WebhookEvent::received(
            provider: $provider,
            eventType: EventType::fromString($event),
            idempotencyKey: $idempotencyKey,
        );
        $receivedEvent = new WebhookReceived(
            provider: $provider->value(),
            event: $event,
            externalId: $externalId,
        );

        $secret = $this->secretResolver->resolve($command->service);

        if (empty($secret)) {
            return ReceiveWebhookResult::secretNotConfigured($provider->value());
        }

        $validation = $this->validateWebhook->handle(new ValidateWebhookCommand(
            service: $command->service,
            payload: $rawPayload,
            signature: $command->signature,
            event: $event,
            externalId: $externalId,
            secret: $secret,
        ));

        if ($validation->isValid()) {
            $webhookEvent->markValidated();
            $webhookEvent->markProcessing();
            $validatedEvent = new WebhookValidated(
                provider: $validation->service,
                event: $validation->event,
                externalId: $validation->externalId,
            );

            $log = $this->recordWebhookLog->handle(new RecordWebhookLogCommand(
                service: $validation->service,
                event: $validation->event,
                valid: $validation->isValid(),
                payload: $validation->payload,
                attempt: DeliveryAttempt::initial()->value(),
                externalId: $externalId,
                idempotencyKey: $idempotencyKey?->value(),
                errorMessage: $validation->errorMessage,
            ));

            $webhookEvent->markProcessed();

            if ($idempotencyKey !== null) {
                $this->processedWebhookRecorder->recordProcessed(
                    service: $provider->value(),
                    idempotencyKey: $idempotencyKey->value(),
                    externalId: $externalId,
                    event: $validation->event,
                );
            }

            return ReceiveWebhookResult::success($log, [
                $receivedEvent,
                $validatedEvent,
                new WebhookLogged(
                    webhookLogId: $log->id,
                    provider: $log->service,
                    event: $log->event,
                    status: $log->status,
                ),
            ]);
        }

        $webhookEvent->markFailed(
            $validation->errorMessage ?? 'Webhook validation failed.'
        );
        $rejectedEvent = new WebhookRejected(
            provider: $validation->service,
            event: $validation->event,
            externalId: $validation->externalId,
            reason: $validation->errorMessage ?? 'Webhook validation failed.',
        );

        $log = $this->recordWebhookLog->handle(new RecordWebhookLogCommand(
            service: $validation->service,
            event: $validation->event,
            valid: false,
            payload: $validation->payload,
            attempt: DeliveryAttempt::initial()->value(),
            externalId: $externalId,
            idempotencyKey: $idempotencyKey?->value(),
            errorMessage: $validation->errorMessage,
        ));

        if ($this->retryConfigurationResolver->resolve()->shouldRetryAsync()) {
            return ReceiveWebhookResult::acceptedForRetry(
                log: $log,
                event: $event,
                secret: $secret,
                externalId: $externalId,
                idempotencyKey: $idempotencyKey?->value(),
                events: [
                    $receivedEvent,
                    $rejectedEvent,
                    new WebhookLogged(
                        webhookLogId: $log->id,
                        provider: $log->service,
                        event: $log->event,
                        status: $log->status,
                    ),
                ],
            );
        }

        return ReceiveWebhookResult::failed(
            log: $log,
            statusCode: $this->failureStatusCode($log->error_message),
            events: [
                $receivedEvent,
                $rejectedEvent,
                new WebhookLogged(
                    webhookLogId: $log->id,
                    provider: $log->service,
                    event: $log->event,
                    status: $log->status,
                ),
            ],
        );
    }

    private function extractEventType(mixed $decodedPayload, WebhookService $service): string
    {
        if (! is_array($decodedPayload)) {
            return 'unknown';
        }

        return $this->payloadParserResolver
            ->forService($service)
            ->extractEventType($decodedPayload);
    }

    private function extractExternalId(
        mixed $decodedPayload,
        WebhookService $service,
        ?string $externalIdHeaderValue
    ): ?string {
        if (! is_array($decodedPayload)) {
            return $externalIdHeaderValue;
        }

        return $this->payloadParserResolver
            ->forService($service)
            ->extractExternalId($decodedPayload, $externalIdHeaderValue);
    }

    private function failureStatusCode(?string $errorMessage): int
    {
        $errorMessage ??= '';

        return str_contains($errorMessage, 'format') || str_contains($errorMessage, 'expired')
            ? Response::HTTP_BAD_REQUEST
            : Response::HTTP_FORBIDDEN;
    }
}
