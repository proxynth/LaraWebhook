<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\UseCases;

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Ingestion\Application\Results\ValidateWebhookResult;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Provider;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Processing\Application\Commands\RetryWebhookCommand;
use Proxynth\Larawebhook\Processing\Application\Data\RetryPolicy;
use Proxynth\Larawebhook\Processing\Application\Ports\AuditLogRecorder;
use Proxynth\Larawebhook\Processing\Application\Ports\RetryPolicyResolver;
use Proxynth\Larawebhook\Processing\Application\Results\RetryWebhookResult;
use Proxynth\Larawebhook\Processing\Domain\Entities\WebhookEvent;
use Proxynth\Larawebhook\Processing\Domain\Events\WebhookProcessingFailed;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\EventType;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\IdempotencyKey;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\ConfiguredWebhookService;

final readonly class RetryWebhook
{
    public function __construct(
        private ValidateWebhook $validateWebhook,
        private AuditLogRecorder $recordWebhookLog,
        private RetryPolicyResolver $retryPolicyResolver,
        private WebhookSecretResolver $secretResolver,
    ) {}

    /**
     * @throws WebhookException
     */
    public function handle(RetryWebhookCommand $command): RetryWebhookResult
    {
        $webhookService = ConfiguredWebhookService::resolve($command->service);

        $rawPayload = RawPayload::fromString($command->payload);
        $retryPolicy = $this->retryPolicyResolver->resolve();
        $secret = $this->secretResolver->resolve($webhookService);

        if ($secret === null) {
            if (! $webhookService instanceof WebhookService) {
                throw new WebhookException("Webhook service '{$command->service}' is not supported.");
            }

            throw new WebhookException("No secret configured for service: {$webhookService->value()}.");
        }

        $validation = $this->validateWebhook->handle(new ValidateWebhookCommand(
            service: $webhookService,
            payload: $rawPayload,
            signature: $command->signature,
            event: $command->event,
            externalId: $command->externalId,
            secret: $secret,
        ));

        $webhookEvent = $this->createWebhookEvent(
            service: $validation->service,
            event: $validation->event,
            idempotencyKey: $command->idempotencyKey,
        );

        if ($validation->isValid()) {
            return $this->handleValidRetry(
                command: $command,
                validation: $validation,
                webhookEvent: $webhookEvent,
            );
        }

        return $this->handleFailedRetry(
            command: $command,
            validation: $validation,
            webhookEvent: $webhookEvent,
            retryPolicy: $retryPolicy,
        );
    }

    private function createWebhookEvent(
        string $service,
        string $event,
        ?string $idempotencyKey,
    ): WebhookEvent {
        return WebhookEvent::received(
            provider: Provider::fromString($service),
            eventType: EventType::fromString($event),
            idempotencyKey: IdempotencyKey::optional($idempotencyKey),
        );
    }

    private function handleValidRetry(
        RetryWebhookCommand $command,
        ValidateWebhookResult $validation,
        WebhookEvent $webhookEvent,
    ): RetryWebhookResult {
        $webhookEvent->markValidated();
        $webhookEvent->markProcessing();

        $log = $this->recordWebhookLog->record(new RecordWebhookLogCommand(
            service: $validation->service,
            event: $validation->event,
            valid: true,
            payload: $validation->payload,
            attempt: $command->attempt,
            externalId: null,
            idempotencyKey: null,
            errorMessage: null,
        ));

        $webhookEvent->markProcessed();

        return RetryWebhookResult::success(
            log: $log,
            events: [
                new WebhookLogged(
                    webhookLogId: $log->id,
                    provider: $log->service,
                    event: $log->event,
                    status: $log->status,
                ),
            ],
        );
    }

    private function handleFailedRetry(
        RetryWebhookCommand $command,
        ValidateWebhookResult $validation,
        WebhookEvent $webhookEvent,
        RetryPolicy $retryPolicy,
    ): RetryWebhookResult {
        $reason = $validation->errorMessage ?? 'Webhook validation failed.';

        $webhookEvent->markFailed($reason);

        $log = $this->recordWebhookLog->record(new RecordWebhookLogCommand(
            service: $validation->service,
            event: $validation->event,
            valid: false,
            payload: $validation->payload,
            attempt: $command->attempt,
            externalId: null,
            idempotencyKey: null,
            errorMessage: $reason,
        ));

        return RetryWebhookResult::failed(
            log: $log,
            shouldRetry: $retryPolicy->shouldRetryAfter($command->attempt),
            nextAttempt: $retryPolicy->nextAttemptAfter($command->attempt),
            delaySeconds: $retryPolicy->delayForAttempt($command->attempt),
            events: [
                new WebhookProcessingFailed(
                    provider: $validation->service,
                    event: $validation->event,
                    externalId: $validation->externalId,
                    attempt: $command->attempt,
                    reason: $reason,
                ),
                new WebhookLogged(
                    webhookLogId: $log->id,
                    provider: $log->service,
                    event: $log->event,
                    status: $log->status,
                ),
            ],
        );
    }
}
