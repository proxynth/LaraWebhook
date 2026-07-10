<?php

namespace Proxynth\Larawebhook\Tests\Fakes\Processing;

use Proxynth\Larawebhook\Processing\Application\Ports\ProcessedWebhookRecorder;
use Proxynth\Larawebhook\Processing\Application\Results\ProcessedWebhookRecordResult;

class FakeProcessedWebhookRecorder implements ProcessedWebhookRecorder
{
    public array $records = [];

    public function __construct(
        private bool $alreadyRecorded = false,
    ) {}

    public function recordProcessed(
        string $service,
        string $idempotencyKey,
        ?string $externalId,
        ?string $event,
    ): ProcessedWebhookRecordResult {
        if ($this->alreadyRecorded) {
            return ProcessedWebhookRecordResult::alreadyRecorded();
        }

        $this->records[] = [
            'service' => $service,
            'idempotencyKey' => $idempotencyKey,
            'externalId' => $externalId,
            'event' => $event,
        ];

        return ProcessedWebhookRecordResult::recorded();
    }
}
