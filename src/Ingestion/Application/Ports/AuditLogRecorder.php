<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Ports;

use Proxynth\Larawebhook\Audit\Application\Ports\WebhookAuditLogWriter;

interface AuditLogRecorder extends WebhookAuditLogWriter {}
