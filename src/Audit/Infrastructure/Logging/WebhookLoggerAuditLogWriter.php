<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Logging;

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookAuditLogWriter;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final readonly class WebhookLoggerAuditLogWriter implements WebhookAuditLogWriter
{
    public function __construct(
        private WebhookLogger $logger,
    ) {}

    public function record(RecordWebhookLogCommand $command): WebhookLog
    {
        if ($command->valid) {
            return $this->logger->logSuccess(
                service: $command->service,
                event: $command->event,
                payload: $command->payload,
                attempt: $command->attempt,
                externalId: $command->externalId,
                idempotencyKey: $command->idempotencyKey,
            );
        }

        return $this->logger->logFailure(
            service: $command->service,
            event: $command->event,
            payload: $command->payload,
            errorMessage: $command->errorMessage ?? 'Webhook validation failed.',
            attempt: $command->attempt,
            externalId: $command->externalId,
            idempotencyKey: $command->idempotencyKey,
        );
    }
}
