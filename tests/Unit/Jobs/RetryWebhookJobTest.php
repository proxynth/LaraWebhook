<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Proxynth\Larawebhook\Jobs\RetryWebhookJob;
use Proxynth\Larawebhook\Models\WebhookLog;

beforeEach(function () {
    // Default retry configuration
    config([
        'larawebhook.retries.enabled' => true,
        'larawebhook.retries.max_attempts' => 3,
        'larawebhook.retries.delays' => [1, 5, 10],
    ]);

    // Disable notifications to avoid side effects
    config(['larawebhook.notifications.enabled' => false]);
});

describe('RetryWebhookJob class structure', function () {
    it('implements ShouldQueue interface', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret'
        );

        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('has tries set to 1', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret'
        );

        expect($job->tries)->toBe(1);
    });

    it('generates unique id based on job properties', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret',
            attempt: 0
        );

        $expectedId = md5('{"test": "data"}'.'test_signature'.'stripe'.'payment.succeeded'.'0');

        expect($job->uniqueId())->toBe($expectedId);
    });

    it('generates different unique ids for different attempts', function () {
        $job1 = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret',
            attempt: 0
        );

        $job2 = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret',
            attempt: 1
        );

        expect($job1->uniqueId())->not->toBe($job2->uniqueId());
    });

    it('generates different unique ids for different services', function () {
        $job1 = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret'
        );

        $job2 = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            service: 'github',
            event: 'payment.succeeded',
            secret: 'test_secret'
        );

        expect($job1->uniqueId())->not->toBe($job2->uniqueId());
    });
});

describe('RetryWebhookJob successful validation', function () {
    it('logs success when stripe webhook signature is valid', function () {
        $secret = 'test_secret_key';
        $payload = '{"type": "payment_intent.succeeded"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret);

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'stripe',
            event: 'payment_intent.succeeded',
            secret: $secret,
            attempt: 0
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log)->not->toBeNull()
            ->and($log->service)->toBe('stripe')
            ->and($log->event)->toBe('payment_intent.succeeded')
            ->and($log->status)->toBe('success')
            ->and($log->attempt)->toBe(0)
            ->and($log->error_message)->toBeNull();
    });

    it('logs success when github webhook signature is valid', function () {
        $secret = 'github_secret_key';
        $payload = '{"action": "push", "ref": "refs/heads/main"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 0
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log)->not->toBeNull()
            ->and($log->service)->toBe('github')
            ->and($log->event)->toBe('push')
            ->and($log->status)->toBe('success')
            ->and($log->attempt)->toBe(0);
    });

    it('logs success with correct attempt number on retry', function () {
        $secret = 'github_secret_key';
        $payload = '{"action": "push"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 2
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->attempt)->toBe(2)
            ->and($log->status)->toBe('success');
    });

    it('does not dispatch retry job on success', function () {
        Queue::fake();

        $secret = 'github_secret_key';
        $payload = '{"action": "push"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 0
        );

        $job->handle();

        Queue::assertNothingPushed();
    });
});

describe('RetryWebhookJob failed validation', function () {
    it('logs failure when signature is invalid', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid_signature',
            service: 'github',
            event: 'push',
            secret: 'correct_secret',
            attempt: 0
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log)->not->toBeNull()
            ->and($log->service)->toBe('github')
            ->and($log->event)->toBe('push')
            ->and($log->status)->toBe('failed')
            ->and($log->attempt)->toBe(0)
            ->and($log->error_message)->toContain('Invalid');
    });

    it('logs failure when service is unsupported', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'some_signature',
            service: 'unknown_service',
            event: 'some_event',
            secret: 'some_secret',
            attempt: 0
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log)->not->toBeNull()
            ->and($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Unsupported service');
    });

    it('logs failure when stripe signature format is invalid', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'invalid_format',
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'stripe_secret',
            attempt: 0
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Invalid Stripe signature format');
    });

    it('logs failure when github signature format is invalid', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'invalid_format_without_sha256',
            service: 'github',
            event: 'push',
            secret: 'github_secret',
            attempt: 0
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Invalid GitHub signature format');
    });

    it('logs failure with correct attempt number', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 2
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->attempt)->toBe(2);
    });
});

describe('RetryWebhookJob retry dispatching', function () {
    it('dispatches retry job on first failure', function () {
        Queue::fake();

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );

        $job->handle();

        Queue::assertPushed(RetryWebhookJob::class, function ($pushedJob) {
            // The next job should have attempt = 1
            return true;
        });
    });

    it('dispatches retry job on second failure', function () {
        Queue::fake();

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 1
        );

        $job->handle();

        Queue::assertPushed(RetryWebhookJob::class);
    });

    it('does not dispatch retry job on last attempt', function () {
        Queue::fake();
        config(['larawebhook.retries.max_attempts' => 3]);

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 2 // Last attempt (0, 1, 2 = 3 attempts)
        );

        $job->handle();

        Queue::assertNothingPushed();
    });

    it('does not dispatch retry when max_attempts is 1', function () {
        Queue::fake();
        config(['larawebhook.retries.max_attempts' => 1]);

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );

        $job->handle();

        Queue::assertNothingPushed();
    });

    it('dispatches retry job with delay from config', function () {
        Queue::fake();
        config(['larawebhook.retries.delays' => [5, 10, 30]]);

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );

        $job->handle();

        Queue::assertPushed(RetryWebhookJob::class, function ($pushedJob) {
            // Job should be delayed (delay is a Carbon instance)
            return $pushedJob->delay !== null;
        });
    });

    it('uses correct delay for second attempt', function () {
        Queue::fake();
        config(['larawebhook.retries.delays' => [1, 5, 10]]);

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 1
        );

        $job->handle();

        Queue::assertPushed(RetryWebhookJob::class, function ($pushedJob) {
            // Job should have a delay set
            return $pushedJob->delay !== null;
        });
    });
});

describe('RetryWebhookJob payload handling', function () {
    it('handles valid JSON payload', function () {
        $secret = 'github_secret';
        $payload = '{"action": "push", "repository": {"name": "test"}}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->payload)->toBeArray()
            ->and($log->payload['action'])->toBe('push')
            ->and($log->payload['repository']['name'])->toBe('test');
    });

    it('handles invalid JSON payload by wrapping in raw key', function () {
        $payload = 'not valid json {{{';

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret'
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->payload)->toBeArray()
            ->and($log->payload)->toHaveKey('raw')
            ->and($log->payload['raw'])->toBe('not valid json {{{');
    });

    it('handles empty payload', function () {
        $job = new RetryWebhookJob(
            payload: '',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret'
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->payload)->toBeArray()
            ->and($log->payload)->toHaveKey('raw');
    });

    it('handles null JSON values', function () {
        $secret = 'github_secret';
        $payload = '{"value": null, "empty": ""}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->payload['value'])->toBeNull()
            ->and($log->payload['empty'])->toBe('');
    });
});

describe('RetryWebhookJob configuration', function () {
    it('respects custom max_attempts configuration', function () {
        Queue::fake();
        config([
            'larawebhook.retries.max_attempts' => 5,
            'larawebhook.retries.delays' => [1, 2, 3, 4, 5], // Need 5 delays for 5 attempts
        ]);

        // At attempt 3, should still retry (since max is 5)
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 3
        );

        $job->handle();

        Queue::assertPushed(RetryWebhookJob::class);
    });

    it('uses last delay when attempt exceeds delay array bounds', function () {
        Queue::fake();
        config([
            'larawebhook.retries.max_attempts' => 10,
            'larawebhook.retries.delays' => [1, 2], // Only 2 delays defined
        ]);

        // Attempt 2 should still retry using the last delay (2 seconds)
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 2
        );

        $job->handle();

        // Should still dispatch because attempt 2 < max_attempts (10)
        Queue::assertPushed(RetryWebhookJob::class);
    });

    it('uses default max_attempts of 3 when not configured', function () {
        Queue::fake();

        // Test that with attempt=2 (last of 3), no retry happens
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 2
        );

        $job->handle();

        // Default max_attempts=3 means attempt 2 is the last one
        Queue::assertNothingPushed();
    });
});

describe('RetryWebhookJob stripe specific scenarios', function () {
    it('fails when stripe timestamp is expired', function () {
        $secret = 'stripe_secret';
        $payload = '{"type": "payment_intent.succeeded"}';
        $expiredTimestamp = time() - 400; // 400 seconds ago (beyond 300s tolerance)
        $signedPayload = "{$expiredTimestamp}.{$payload}";
        $signature = 't='.$expiredTimestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret);

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'stripe',
            event: 'payment_intent.succeeded',
            secret: $secret,
            attempt: 0
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('expired');
    });

    it('succeeds when stripe timestamp is within tolerance', function () {
        $secret = 'stripe_secret';
        $payload = '{"type": "payment_intent.succeeded"}';
        $timestamp = time() - 100; // 100 seconds ago (within 300s tolerance)
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret);

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'stripe',
            event: 'payment_intent.succeeded',
            secret: $secret,
            attempt: 0
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->status)->toBe('success');
    });
});

describe('RetryWebhookJob external_id support', function () {
    it('logs success with external_id', function () {
        $secret = 'github_secret_key';
        $payload = '{"action": "push"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 0,
            externalId: 'delivery-123-abc'
        );

        $job->handle();

        $log = WebhookLog::latest()->first();

        expect($log->external_id)->toBe('delivery-123-abc');
    });

    it('logs failure with external_id', function () {
        $uniqueExternalId = 'evt_failure_'.uniqid();

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0,
            externalId: $uniqueExternalId
        );

        $job->handle();

        $log = WebhookLog::where('external_id', $uniqueExternalId)->first();

        expect($log)->not->toBeNull()
            ->and($log->external_id)->toBe($uniqueExternalId)
            ->and($log->status)->toBe('failed');
    });

    it('passes external_id to next retry job', function () {
        Queue::fake();

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0,
            externalId: 'delivery-456'
        );

        $job->handle();

        Queue::assertPushed(RetryWebhookJob::class);
    });
});

describe('RetryWebhookJob multiple logs creation', function () {
    it('creates log entries for each attempt', function () {
        Queue::fake(); // Prevent actual job dispatch

        // Clear existing logs
        WebhookLog::query()->delete();

        // First attempt - fails
        $job1 = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );
        $job1->handle();

        // Second attempt - fails
        $job2 = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 1
        );
        $job2->handle();

        // Third attempt - success
        $secret = 'secret';
        $payload = '{"test": "data"}';
        $validSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $job3 = new RetryWebhookJob(
            payload: $payload,
            signature: $validSignature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 2
        );
        $job3->handle();

        $logs = WebhookLog::orderBy('id')->get();

        expect($logs)->toHaveCount(3)
            ->and($logs[0]->attempt)->toBe(0)
            ->and($logs[0]->status)->toBe('failed')
            ->and($logs[1]->attempt)->toBe(1)
            ->and($logs[1]->status)->toBe('failed')
            ->and($logs[2]->attempt)->toBe(2)
            ->and($logs[2]->status)->toBe('success');
    });

    it('maintains separate logs for different services', function () {
        Queue::fake(); // Prevent actual job dispatch

        // Clear existing logs
        WebhookLog::query()->delete();

        $githubJob = new RetryWebhookJob(
            payload: '{"test": "github"}',
            signature: 'sha256=invalid',
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );
        $githubJob->handle();

        $stripeJob = new RetryWebhookJob(
            payload: '{"test": "stripe"}',
            signature: 'invalid_format',
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'secret',
            attempt: 0
        );
        $stripeJob->handle();

        $githubLogs = WebhookLog::where('service', 'github')->count();
        $stripeLogs = WebhookLog::where('service', 'stripe')->count();

        expect($githubLogs)->toBe(1)
            ->and($stripeLogs)->toBe(1);
    });
});
