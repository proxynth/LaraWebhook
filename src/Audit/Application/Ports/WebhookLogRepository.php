<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Ports;

use Carbon\CarbonInterface;

interface WebhookLogRepository
{
    public function countOlderThan(CarbonInterface $cutoff): int;

    public function deleteOlderThan(CarbonInterface $cutoff): int;
}
