<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Logging;

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookAuditLogWriter;
use Proxynth\Larawebhook\Ingestion\Application\Ports\AuditLogRecorder as IngestionAuditLogRecorder;
use Proxynth\Larawebhook\Processing\Application\Ports\AuditLogRecorder as ProcessingAuditLogRecorder;

final readonly class WebhookLoggerAuditLogWriter implements IngestionAuditLogRecorder, ProcessingAuditLogRecorder, WebhookAuditLogWriter
{
    public function __construct(
        private WebhookLogger $logger,
    ) {}

    public function record(RecordWebhookLogCommand $command): WebhookLogData
    {
        if ($command->valid) {
            $log = $this->logger->logSuccess(
                service: $command->service,
                event: $command->event,
                payload: $command->payload,
                attempt: $command->attempt,
                externalId: $command->externalId,
                idempotencyKey: $command->idempotencyKey,
            );
        } else {
            $log = $this->logger->logFailure(
                service: $command->service,
                event: $command->event,
                payload: $command->payload,
                errorMessage: $command->errorMessage ?? 'Webhook validation failed.',
                attempt: $command->attempt,
                externalId: $command->externalId,
                idempotencyKey: $command->idempotencyKey,
            );
        }

        return WebhookLogDataFactory::fromModel($log);
    }
}
