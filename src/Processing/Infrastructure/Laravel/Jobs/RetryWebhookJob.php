<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Processing\Application\Commands\RetryWebhookCommand;
use Proxynth\Larawebhook\Processing\Application\UseCases\RetryWebhook;
use Proxynth\Larawebhook\Shared\Application\Ports\EventBus;

class RetryWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $payload,
        private readonly Signature $signature,
        private readonly string $service,
        private readonly string $event,
        private readonly int $attempt = 0,
        private readonly ?string $externalId = null,
        private readonly ?string $idempotencyKey = null,
    ) {}

    /**
     * Execute the job.
     *
     * @throws WebhookException
     */
    public function handle(
        RetryWebhook $retryWebhook,
        EventBus $eventBus,
    ): void {
        $result = $retryWebhook->handle(new RetryWebhookCommand(
            payload: $this->payload,
            signature: $this->signature,
            service: $this->service,
            event: $this->event,
            attempt: $this->attempt,
            externalId: $this->externalId,
            idempotencyKey: $this->idempotencyKey,
        ));

        $eventBus->dispatchMany($result->events);

        if (! $result->shouldRetry) {
            return;
        }

        self::dispatch(
            payload: $this->payload,
            signature: $this->signature,
            service: $this->service,
            event: $this->event,
            attempt: $result->nextAttempt ?? $this->attempt + 1,
            externalId: $this->externalId,
            idempotencyKey: $this->idempotencyKey,
        )->delay(now()->addSeconds($result->delaySeconds ?? 1));
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return md5($this->payload.$this->signature->value().$this->service.$this->event.$this->attempt);
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function externalId(): ?string
    {
        return $this->externalId;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function service(): string
    {
        return $this->service;
    }

    public function event(): string
    {
        return $this->event;
    }

    public function payload(): string
    {
        return $this->payload;
    }
}
