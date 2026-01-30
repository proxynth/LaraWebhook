<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
        private readonly int $attempt = 0,
        private readonly ?string $externalId = null
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

        Log::info('RetryWebhookJob: Attempting webhook validation', [
            'service' => $this->service,
            'event' => $this->event,
            'attempt' => $this->attempt,
            'external_id' => $this->externalId,
        ]);

        try {
            // Try to validate
            $validator->validate($this->payload, $this->signature, $this->service);

            // Success - log it
            $logger->logSuccess(
                $this->service,
                $this->event,
                $decodedPayload,
                $this->attempt,
                $this->externalId
            );

            Log::info('RetryWebhookJob: Webhook validation succeeded on retry', [
                'service' => $this->service,
                'event' => $this->event,
                'attempt' => $this->attempt,
            ]);
        } catch (WebhookException|InvalidSignatureException $e) {
            // Log the failure
            $logger->logFailure(
                $this->service,
                $this->event,
                $decodedPayload,
                $e->getMessage(),
                $this->attempt,
                $this->externalId
            );

            // If not the last attempt, dispatch a new retry job with delay
            if ($this->attempt < $maxAttempts - 1) {
                $nextDelay = $delays[$this->attempt] ?? $delays[count($delays) - 1] ?? 10;

                Log::info('RetryWebhookJob: Scheduling next retry', [
                    'service' => $this->service,
                    'next_attempt' => $this->attempt + 1,
                    'delay_seconds' => $nextDelay,
                ]);

                // Don't pass external_id to subsequent retries to avoid unique constraint violation
                // The initial log entry already has the external_id
                self::dispatch(
                    $this->payload,
                    $this->signature,
                    $this->service,
                    $this->event,
                    $this->secret,
                    $this->attempt + 1,
                    null
                )->delay(now()->addSeconds($nextDelay));
            } else {
                Log::warning('RetryWebhookJob: All retry attempts exhausted', [
                    'service' => $this->service,
                    'event' => $this->event,
                    'total_attempts' => $this->attempt + 1,
                    'error' => $e->getMessage(),
                ]);
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
