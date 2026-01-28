<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Services\WebhookValidator;

beforeEach(function () {
    $this->secret = 'test_secret_key_123';
    $this->validator = new WebhookValidator($this->secret, 300);

    // Set retry config for tests
    config([
        'larawebhook.retries.enabled' => true,
        'larawebhook.retries.max_attempts' => 3,
        'larawebhook.retries.delays' => [0, 0, 0], // No delay in tests for speed
    ]);
});

describe('validateWithRetries success scenarios', function () {
    it('succeeds on first attempt and logs attempt 0', function () {
        $payload = '{"event": "payment_intent.succeeded"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$timestamp},v1={$computedSignature}";

        $log = $this->validator->validateWithRetries(
            $payload,
            $signatureHeader,
            'stripe',
            'payment_intent.succeeded'
        );

        expect($log->status)->toBe('success')
            ->and($log->attempt)->toBe(0)
            ->and($log->error_message)->toBeNull()
            // Only one log entry should exist
            ->and(WebhookLog::count())->toBe(1);
    });

    it('succeeds on first GitHub webhook attempt', function () {
        $payload = '{"action": "opened"}';
        $computedSignature = hash_hmac('sha256', $payload, $this->secret);
        $signatureHeader = "sha256={$computedSignature}";

        $log = $this->validator->validateWithRetries(
            $payload,
            $signatureHeader,
            'github',
            'pull_request.opened'
        );

        expect($log->status)->toBe('success')
            ->and($log->attempt)->toBe(0);
    });
});

describe('validateWithRetries failure and retry scenarios', function () {
    it('logs all 3 attempts when validation always fails', function () {
        $payload = '{"event": "test"}';
        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef";

        try {
            $this->validator->validateWithRetries(
                $payload,
                $signatureHeader,
                'stripe',
                'test.event'
            );
            expect(false)->toBeTrue('Should have thrown exception');
        } catch (InvalidSignatureException $e) {
            // Expected exception after all retries
            expect($e->getMessage())->toContain('Invalid Stripe webhook signature');
        }

        // Should have 3 failed log entries (attempt 0, 1, 2)
        $logs = WebhookLog::orderBy('attempt')->get();
        expect($logs)->toHaveCount(3)
            ->and($logs[0]->attempt)->toBe(0)
            ->and($logs[0]->status)->toBe('failed')
            ->and($logs[1]->attempt)->toBe(1)
            ->and($logs[1]->status)->toBe('failed')
            ->and($logs[2]->attempt)->toBe(2)
            ->and($logs[2]->status)->toBe('failed');
    });

    it('logs error message on all failed attempts', function () {
        $payload = '{"event": "test"}';
        $signatureHeader = 'malformed_signature';

        try {
            $this->validator->validateWithRetries(
                $payload,
                $signatureHeader,
                'stripe',
                'test.event'
            );
        } catch (\Exception $e) {
            // Expected
        }

        $logs = WebhookLog::all();
        expect($logs)->toHaveCount(3);

        foreach ($logs as $log) {
            expect($log->error_message)->not->toBeNull()
                ->and($log->error_message)->toContain('Invalid Stripe signature format');
        }
    });
});

describe('validateWithRetries with retry configuration', function () {
    it('respects max_attempts configuration', function () {
        config(['larawebhook.retries.max_attempts' => 2]);

        $payload = '{"event": "test"}';
        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef";

        try {
            $this->validator->validateWithRetries(
                $payload,
                $signatureHeader,
                'stripe',
                'test.event'
            );
        } catch (\Exception $e) {
            // Expected
        }

        // Should only have 2 attempts (0 and 1)
        expect(WebhookLog::count())->toBe(2)
            ->and(WebhookLog::orderBy('attempt')->pluck('attempt')->toArray())->toBe([0, 1]);
    });

    it('respects enabled configuration', function () {
        config(['larawebhook.retries.enabled' => false]);

        $payload = '{"event": "test"}';
        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef";

        try {
            $this->validator->validateWithRetries(
                $payload,
                $signatureHeader,
                'stripe',
                'test.event'
            );
        } catch (\Exception $e) {
            // Expected
        }

        // When retries are disabled, should only have 1 log (no retries)
        expect(WebhookLog::count())->toBe(1)
            ->and(WebhookLog::first()->attempt)->toBe(0);
    });

    it('can configure custom delays', function () {
        config(['larawebhook.retries.delays' => [0, 0, 0]]);

        $payload = '{"event": "test"}';
        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=invalid";

        $start = microtime(true);
        try {
            $this->validator->validateWithRetries(
                $payload,
                $signatureHeader,
                'stripe',
                'test.event'
            );
        } catch (\Exception $e) {
            // Expected
        }
        $duration = microtime(true) - $start;

        // With 0 delays, should be very fast (< 0.5 seconds)
        expect($duration)->toBeLessThan(0.5);
        expect(WebhookLog::count())->toBe(3);
    });
});

describe('validateWithRetries scope queries', function () {
    it('can query retried webhooks', function () {
        $payload = '{"event": "test"}';
        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=invalid";

        try {
            $this->validator->validateWithRetries(
                $payload,
                $signatureHeader,
                'stripe',
                'test.event'
            );
        } catch (\Exception $e) {
            // Expected
        }

        // Should have logs with attempt > 0
        $retriedLogs = WebhookLog::retried()->get();
        expect($retriedLogs)->toHaveCount(2) // attempts 1 and 2
            ->and($retriedLogs->min('attempt'))->toBe(1)
            ->and($retriedLogs->max('attempt'))->toBe(2);
    });

    it('can filter by specific attempt number', function () {
        $payload = '{"event": "test"}';
        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=invalid";

        try {
            $this->validator->validateWithRetries(
                $payload,
                $signatureHeader,
                'stripe',
                'test.event'
            );
        } catch (\Exception $e) {
            // Expected
        }

        $attempt1Logs = WebhookLog::attempt(1)->get();
        expect($attempt1Logs)->toHaveCount(1)
            ->and($attempt1Logs->first()->attempt)->toBe(1);

        $attempt2Logs = WebhookLog::attempt(2)->get();
        expect($attempt2Logs)->toHaveCount(1)
            ->and($attempt2Logs->first()->attempt)->toBe(2);
    });
});

describe('validateWithRetries edge cases', function () {
    it('throws exception after all retries exhausted', function () {
        $payload = '{"event": "test"}';
        $signatureHeader = 'sha256=invalid';

        expect(fn () => $this->validator->validateWithRetries(
            $payload,
            $signatureHeader,
            'github',
            'test.event'
        ))->toThrow(InvalidSignatureException::class);
    });

    it('handles expired webhooks with retries', function () {
        $payload = '{"event": "test"}';
        $expiredTimestamp = time() - 400;
        $signedPayload = "{$expiredTimestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$expiredTimestamp},v1={$computedSignature}";

        try {
            $this->validator->validateWithRetries(
                $payload,
                $signatureHeader,
                'stripe',
                'test.event'
            );
        } catch (\Exception $e) {
            // Expected
        }

        // All 3 attempts should fail with "expired" message
        $logs = WebhookLog::all();
        expect($logs)->toHaveCount(3);

        foreach ($logs as $log) {
            expect($log->error_message)->toContain('Webhook is expired');
        }
    });

    it('throws fallback exception when max_attempts is zero', function () {
        config(['larawebhook.retries.max_attempts' => 0]);

        $payload = '{"event": "test"}';
        $signatureHeader = 'sha256=invalid';

        expect(fn () => $this->validator->validateWithRetries(
            $payload,
            $signatureHeader,
            'github',
            'test.event'
        ))->toThrow(WebhookException::class, 'Validation failed with no recorded exception.');

        // No logs should be created since no attempts were made
        expect(WebhookLog::count())->toBe(0);
    });

    it('throws fallback exception when max_attempts is negative', function () {
        config(['larawebhook.retries.max_attempts' => -1]);

        $payload = '{"event": "test"}';
        $signatureHeader = 'sha256=invalid';

        expect(fn () => $this->validator->validateWithRetries(
            $payload,
            $signatureHeader,
            'github',
            'test.event'
        ))->toThrow(WebhookException::class, 'Validation failed with no recorded exception.');

        expect(WebhookLog::count())->toBe(0);
    });
});
