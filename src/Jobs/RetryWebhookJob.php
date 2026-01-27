<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Services\WebhookLogger;
use Proxynth\Larawebhook\Services\WebhookValidator;

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
        private readonly string $signature,
        private readonly string $service,
        private readonly string $event,
        private readonly string $secret,
        private readonly int $attempt = 0
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $validator = new WebhookValidator($this->secret);
        $logger = new WebhookLogger;
        $decodedPayload = json_decode($this->payload, true) ?? ['raw' => $this->payload];

        $maxAttempts = config('larawebhook.retries.max_attempts', 3);
        $delays = config('larawebhook.retries.delays', [1, 5, 10]);

        try {
            // Try to validate
            $validator->validate($this->payload, $this->signature, $this->service);

            // Success - log it
            $logger->logSuccess($this->service, $this->event, $decodedPayload, $this->attempt);
        } catch (WebhookException|InvalidSignatureException $e) {
            // Log the failure
            $logger->logFailure(
                $this->service,
                $this->event,
                $decodedPayload,
                $e->getMessage(),
                $this->attempt
            );

            // If not the last attempt, dispatch a new retry job with delay
            if ($this->attempt < $maxAttempts - 1 && isset($delays[$this->attempt])) {
                self::dispatch(
                    $this->payload,
                    $this->signature,
                    $this->service,
                    $this->event,
                    $this->secret,
                    $this->attempt + 1
                )->delay($delays[$this->attempt]);
            }
        }
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return md5($this->payload.$this->signature.$this->service.$this->event.$this->attempt);
    }
}
