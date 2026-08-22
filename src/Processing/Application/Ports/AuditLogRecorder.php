<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Ports;

use Proxynth\Larawebhook\Audit\Application\Ports\WebhookAuditLogWriter;

interface AuditLogRecorder extends WebhookAuditLogWriter {}
