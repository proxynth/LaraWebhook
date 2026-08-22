<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Ports;

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;

interface WebhookAuditLogWriter
{
    public function record(RecordWebhookLogCommand $command): WebhookLogData;
}
