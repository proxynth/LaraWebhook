<?php

use Carbon\CarbonInterface;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogRepository;

final class FakeWebhookLogRepository implements WebhookLogRepository
{
    public int $countOlderThanCalls = 0;

    public int $deleteOlderThanCalls = 0;

    public ?CarbonInterface $lastCountCutoff = null;

    public ?CarbonInterface $lastDeleteCutoff = null;

    public function __construct(
        private readonly int $count = 0,
        private readonly int $deleted = 0,
    ) {}

    public function countOlderThan(CarbonInterface $cutoff): int
    {
        $this->countOlderThanCalls++;
        $this->lastCountCutoff = $cutoff;

        return $this->count;
    }

    public function deleteOlderThan(CarbonInterface $cutoff): int
    {
        $this->deleteOlderThanCalls++;
        $this->lastDeleteCutoff = $cutoff;

        return $this->deleted;
    }
}
