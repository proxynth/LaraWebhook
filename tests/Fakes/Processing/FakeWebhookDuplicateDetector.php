<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Tests\Fakes\Processing;

use Proxynth\Larawebhook\Processing\Application\Ports\WebhookDuplicateDetector;

final class FakeWebhookDuplicateDetector implements WebhookDuplicateDetector
{
    /**
     * @var array<string, bool>
     */
    private array $responses = [];

    /**
     * @var array<int, array{service: string, idempotency_key: string}>
     */
    public array $calls = [];

    public function alreadyProcessed(string $service, string $idempotencyKey): bool
    {
        $this->calls[] = [
            'service' => $service,
            'idempotency_key' => $idempotencyKey,
        ];

        return $this->responses[$this->key($service, $idempotencyKey)] ?? false;
    }

    public function shouldAlreadyProcessed(string $service, string $idempotencyKey, bool $value = true): void
    {
        $this->responses[$this->key($service, $idempotencyKey)] = $value;
    }

    private function key(string $service, string $idempotencyKey): string
    {
        return $service.'|'.$idempotencyKey;
    }
}
