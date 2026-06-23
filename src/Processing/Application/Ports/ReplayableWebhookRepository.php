<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Application\Ports;

use Proxynth\Larawebhook\Processing\Application\DTOs\ReplayableWebhook;

interface ReplayableWebhookRepository
{
    public function findReplayableById(int|string $id): ReplayableWebhook;
}
