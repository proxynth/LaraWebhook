<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\UseCases;

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Audit\Domain\Exceptions\PayloadNotAvailable;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Processing\Application\Commands\ReplayWebhookCommand;

final readonly class ReplayWebhook
{
    public function __construct(
        private ValidateWebhook $validateWebhook,
        private RecordWebhookLog $recordWebhookLog,
    ) {}

    /**
     * @throws WebhookException
     * @throws \JsonException
     * @throws \Exception
     */
    public function handle(ReplayWebhookCommand $command): WebhookLog
    {
        $log = $command->log;
        $webhookService = WebhookService::fromString($log->service);

        if ($log->payload === null || $log->payload === []) {
            throw PayloadNotAvailable::forWebhookLog($log->id);
        }

        $validation = $this->validateWebhook->handle(new ValidateWebhookCommand(
            service: $webhookService,
            payload: RawPayload::fromArray($log->payload),
            signature: $command->signature,
            event: $log->event,
            externalId: $log->external_id,
            secret: $webhookService->secret() ?? ''
        ));

        return $this->recordWebhookLog->handle(new RecordWebhookLogCommand(
            service: $validation->service,
            event: $validation->event,
            valid: $validation->isValid(),
            payload: $validation->payload,
            attempt: $log->attempt + 1,
            externalId: $log->external_id,
            errorMessage: $validation->errorMessage,
        ));
    }
}
