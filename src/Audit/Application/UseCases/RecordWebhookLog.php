<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\UseCases;

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookAuditLogWriter;

final readonly class RecordWebhookLog
{
    public function __construct(
        private WebhookAuditLogWriter $writer,
    ) {}

    public function handle(RecordWebhookLogCommand $command): WebhookLogData
    {
        return $this->writer->record($command);
    }
}
