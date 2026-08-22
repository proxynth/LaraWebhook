<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\UseCases;

use Exception;
use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ReceiveWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Ports\AuditLogRecorder;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookPayloadParserResolver;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Ingestion\Application\Results\ReceiveWebhookResult;
use Proxynth\Larawebhook\Ingestion\Application\Services\ReceiveWebhookEventFactory;
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
use Proxynth\Larawebhook\Shared\Application\Ports\TransactionRunner;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;
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
        private AuditLogRecorder $recordWebhookLog,
        private TransactionRunner $transactionRunner,
        private ReceiveWebhookEventFactory $eventFactory,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(ReceiveWebhookCommand $command): ReceiveWebhookResult
    {
        return $this->transactionRunner->run(fn (): ReceiveWebhookResult => $this->handleInTransaction($command));
    }

    private function handleInTransaction(ReceiveWebhookCommand $command): ReceiveWebhookResult
    {
        $provider = Provider::fromString($command->service->value());
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
        $receivedEvent = $this->eventFactory->received($provider->value(), $event, $externalId);

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
            $validatedEvent = $this->eventFactory->validated(
                $validation->service,
                $validation->event,
                $validation->externalId,
            );

            if ($idempotencyKey !== null) {
                $recordResult = $this->processedWebhookRecorder->recordProcessed(
                    service: $provider->value(),
                    idempotencyKey: $idempotencyKey->value(),
                    externalId: $externalId,
                    event: $validation->event,
                );

                if ($recordResult->alreadyRecorded) {
                    return ReceiveWebhookResult::alreadyProcessed(
                        externalId: $externalId,
                        idempotencyKey: $idempotencyKey->value(),
                    );
                }
            }

            $log = $this->recordWebhookLog->record(new RecordWebhookLogCommand(
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

            return ReceiveWebhookResult::success($log, [
                $receivedEvent,
                $validatedEvent,
                $this->eventFactory->logged($log),
            ]);
        }

        $webhookEvent->markFailed(
            $validation->errorMessage ?? 'Webhook validation failed.'
        );
        $rejectedEvent = $this->eventFactory->rejected(
            $validation->service,
            $validation->event,
            $validation->externalId,
            $validation->errorMessage ?? 'Webhook validation failed.',
        );

        $log = $this->recordWebhookLog->record(new RecordWebhookLogCommand(
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
                externalId: $externalId,
                idempotencyKey: $idempotencyKey?->value(),
                events: [
                    $receivedEvent,
                    $rejectedEvent,
                    $this->eventFactory->logged($log),
                ],
            );
        }

        return ReceiveWebhookResult::failed(
            log: $log,
            statusCode: $this->failureStatusCode($log->errorMessage),
            events: [
                $receivedEvent,
                $rejectedEvent,
                $this->eventFactory->logged($log),
            ],
        );
    }

    private function extractEventType(mixed $decodedPayload, WebhookServiceIdentifier $service): string
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
        WebhookServiceIdentifier $service,
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
