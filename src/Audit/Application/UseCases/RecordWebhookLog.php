<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\UseCases;

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookAuditLogWriter;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final readonly class RecordWebhookLog
{
    public function __construct(
        private WebhookAuditLogWriter $writer,
    ) {}

    public function handle(RecordWebhookLogCommand $command): WebhookLog
    {
        return $this->writer->record($command);
    }
}
