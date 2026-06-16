<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence;

use Carbon\CarbonInterface;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogRepository;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final class EloquentWebhookLogRepository implements WebhookLogRepository
{
    public function countOlderThan(CarbonInterface $cutoff): int
    {
        return WebhookLog::query()
            ->where('created_at', '<', $cutoff)
            ->count();
    }

    public function deleteOlderThan(CarbonInterface $cutoff): int
    {
        return WebhookLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
