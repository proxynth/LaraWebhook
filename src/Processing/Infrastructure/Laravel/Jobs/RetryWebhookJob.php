<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;

class RetryWebhookJob implements ShouldQueue
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
        private readonly string $secret,
        private readonly int $attempt = 0,
        private readonly ?string $externalId = null,
        private readonly ?string $idempotencyKey = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ValidateWebhook $validateWebhook, RecordWebhookLog $recordWebhookLog): void
    {
        $maxAttempts = (int) config('larawebhook.retries.max_attempts', 3);
        $delays = config('larawebhook.retries.delays', [1, 5, 10]);
        $delays = is_array($delays) ? $delays : [1, 5, 10];

        $webhookService = WebhookService::tryFromString($this->service);
        if ($webhookService === null) {
            throw new WebhookException("Webhook service '{$this->service}' is not supported.");
        }

        $rawPayload = RawPayload::fromString($this->payload);

        Log::info('RetryWebhookJob: Attempting webhook validation', [
            'service' => $this->service,
            'event' => $this->event,
            'attempt' => $this->attempt,
            'external_id' => $this->externalId,
        ]);

        // Try to validate
        $validation = $validateWebhook->handle(new ValidateWebhookCommand(
            service: $webhookService,
            payload: $rawPayload,
            signature: $this->signature,
            event: $this->event,
            externalId: $this->externalId,
            secret: $this->secret,
        ));

        $recordWebhookLog->handle(new RecordWebhookLogCommand(
            service: $validation->service,
            event: $validation->event,
            valid: $validation->isValid(),
            payload: $validation->payload,
            attempt: $this->attempt,
            externalId: null,
            idempotencyKey: null,
            errorMessage: $validation->errorMessage,
        ));

        if ($validation->isValid()) {
            Log::info('RetryWebhookJob: Webhook validation succeeded on retry', [
                'service' => $this->service,
                'event' => $this->event,
                'attempt' => $this->attempt,
            ]);

            return;
        }

        if ($this->attempt < $maxAttempts - 1) {
            $nextDelay = $delays[$this->attempt] ?? $delays[count($delays) - 1] ?? 10;

            Log::info('RetryWebhookJob: Scheduling next retry', [
                'service' => $this->service,
                'next_attempt' => $this->attempt + 1,
                'delay_seconds' => $nextDelay,
            ]);

            self::dispatch(
                payload: $this->payload,
                signature: $this->signature,
                service: $this->service,
                event: $this->event,
                secret: $this->secret,
                attempt: $this->attempt + 1,
                externalId: $this->externalId,
                idempotencyKey: $this->idempotencyKey,
            )->delay(now()->addSeconds($nextDelay));
        }

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
