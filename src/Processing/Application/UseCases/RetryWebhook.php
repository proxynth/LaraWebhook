<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\UseCases;

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Processing\Application\Commands\RetryWebhookCommand;
use Proxynth\Larawebhook\Processing\Application\Ports\RetryPolicyResolver;
use Proxynth\Larawebhook\Processing\Application\Results\RetryWebhookResult;
use Proxynth\Larawebhook\Processing\Domain\Events\WebhookProcessingFailed;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

final readonly class RetryWebhook
{
    public function __construct(
        private ValidateWebhook $validateWebhook,
        private RecordWebhookLog $recordWebhookLog,
        private RetryPolicyResolver $retryPolicyResolver,
    ) {}

    /**
     * @throws WebhookException
     */
    public function handle(RetryWebhookCommand $command): RetryWebhookResult
    {
        $webhookService = WebhookService::tryFromString($command->service);

        if ($webhookService === null) {
            throw new WebhookException("Webhook service '{$command->service}' is not supported.");
        }

        $rawPayload = RawPayload::fromString($command->payload);
        $retryPolicy = $this->retryPolicyResolver->resolve();

        $validation = $this->validateWebhook->handle(new ValidateWebhookCommand(
            service: $webhookService,
            payload: $rawPayload,
            signature: $command->signature,
            event: $command->event,
            externalId: $command->externalId,
            secret: $command->secret,
        ));

        $log = $this->recordWebhookLog->handle(new RecordWebhookLogCommand(
            service: $validation->service,
            event: $validation->event,
            valid: $validation->isValid(),
            payload: $validation->payload,
            attempt: $command->attempt,
            externalId: null,
            idempotencyKey: null,
            errorMessage: $validation->errorMessage,
        ));

        if ($validation->isValid()) {
            return RetryWebhookResult::success($log, [
                new WebhookLogged(
                    webhookLogId: $log->id,
                    provider: $log->service,
                    event: $log->event,
                    status: $log->status,
                ),
            ]);
        }

        $shouldRetry = $retryPolicy->shouldRetryAfter($command->attempt);

        return RetryWebhookResult::failed(
            log: $log,
            shouldRetry: $shouldRetry,
            nextAttempt: $retryPolicy->nextAttemptAfter($command->attempt),
            delaySeconds: $retryPolicy->delayForAttempt($command->attempt),
            events: [
                new WebhookProcessingFailed(
                    provider: $validation->service,
                    event: $validation->event,
                    externalId: $validation->externalId,
                    attempt: $command->attempt,
                    reason: $validation->errorMessage ?? 'Webhook validation failed.',
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
