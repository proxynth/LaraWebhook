<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\UseCases;

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Audit\Domain\Exceptions\PayloadNotAvailable;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Processing\Application\Commands\ReplayWebhookCommand;
use Proxynth\Larawebhook\Processing\Application\Exceptions\ReplayWebhookNotAllowed;
use Proxynth\Larawebhook\Processing\Application\Ports\AuditLogRecorder;
use Proxynth\Larawebhook\Processing\Application\Ports\ReplayableWebhookRepository;
use Proxynth\Larawebhook\Processing\Application\Results\ReplayWebhookResult;
use Proxynth\Larawebhook\Processing\Domain\Events\WebhookReplayed;
use Proxynth\Larawebhook\Processing\Domain\Exceptions\InvalidWebhookState;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\ConfiguredWebhookService;

final readonly class ReplayWebhook
{
    public function __construct(
        private ReplayableWebhookRepository $replayableWebhookRepository,
        private ValidateWebhook $validateWebhook,
        private AuditLogRecorder $recordWebhookLog,
        private WebhookSecretResolver $secretResolver,
    ) {}

    /**
     * @throws WebhookException
     * @throws \JsonException
     * @throws \Exception
     */
    public function handle(ReplayWebhookCommand $command): ReplayWebhookResult
    {
        $replayableWebhook = $this->replayableWebhookRepository->findReplayableById($command->webhookLogId);

        if ($replayableWebhook->payload === null || $replayableWebhook->payload === []) {
            throw PayloadNotAvailable::forWebhookLog($replayableWebhook->id);
        }

        try {
            $replayedEvent = $replayableWebhook->toWebhookEvent()->replay();
        } catch (InvalidWebhookState) {
            throw ReplayWebhookNotAllowed::forWebhook(
                $replayableWebhook->id,
                $replayableWebhook->status
            );
        }

        $webhookService = ConfiguredWebhookService::resolve($replayableWebhook->service);
        $secret = $this->secretResolver->resolve($webhookService);

        if ($secret === null) {
            if (! $webhookService instanceof WebhookService) {
                throw new WebhookException("Webhook service '{$replayableWebhook->service}' is not supported.");
            }

            throw new WebhookException("No secret configured for service: {$webhookService->value()}.");
        }

        $validation = $this->validateWebhook->handle(new ValidateWebhookCommand(
            service: $webhookService,
            payload: RawPayload::fromArray($replayableWebhook->payload),
            signature: $command->signature,
            event: $replayedEvent->eventType()->value(),
            externalId: $replayableWebhook->externalId,
            secret: $secret,
        ));

        $log = $this->recordWebhookLog->record(new RecordWebhookLogCommand(
            service: $validation->service,
            event: $validation->event,
            valid: $validation->isValid(),
            payload: $validation->payload,
            attempt: $replayableWebhook->attempt + 1,
            externalId: null,
            idempotencyKey: null,
            errorMessage: $validation->errorMessage,
        ));

        return ReplayWebhookResult::fromData(
            $log,
            $validation->errorMessage,
            [
                new WebhookReplayed(
                    webhookLogId: $log->id,
                    provider: $log->service,
                    event: $log->event,
                    attempt: $replayableWebhook->attempt + 1,
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
