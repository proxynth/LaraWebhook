<?php

namespace Proxynth\Larawebhook\Tests\Fakes\Processing;

use Proxynth\Larawebhook\Processing\Application\Ports\ProcessedWebhookRecorder;

class FakeProcessedWebhookRecorder implements ProcessedWebhookRecorder
{
    public array $records = [];

    public function recordProcessed(
        string $service,
        string $idempotencyKey,
        ?string $externalId,
        ?string $event,
    ): void {
        $this->records[] = [
            'service' => $service,
            'idempotencyKey' => $idempotencyKey,
            'externalId' => $externalId,
            'event' => $event,
        ];
    }
}
