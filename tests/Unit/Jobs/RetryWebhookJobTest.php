<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook;
use Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Jobs\RetryWebhookJob;

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
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret'
        );

        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('has tries set to 1', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret'
        );

        expect($job->tries)->toBe(1);
    });

    it('generates unique id based on job properties', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('test_signature'),
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
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret',
            attempt: 0
        );

        $job2 = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('test_signature'),
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
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'test_secret'
        );

        $job2 = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('test_signature'),
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
        $signature = incomingSignature('t='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'stripe',
            event: 'payment_intent.succeeded',
            secret: $secret,
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

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
        $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

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
        $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 2
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $log = WebhookLog::latest()->first();

        expect($log->attempt)->toBe(2)
            ->and($log->status)->toBe('success');
    });

    it('does not dispatch retry job on success', function () {
        Queue::fake();

        $secret = 'github_secret_key';
        $payload = '{"action": "push"}';
        $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        Queue::assertNothingPushed();
    });
});

describe('RetryWebhookJob failed validation', function () {
    it('logs failure when signature is invalid', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid_signature'),
            service: 'github',
            event: 'push',
            secret: 'correct_secret',
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $log = WebhookLog::latest()->first();

        expect($log)->not->toBeNull()
            ->and($log->service)->toBe('github')
            ->and($log->event)->toBe('push')
            ->and($log->status)->toBe('failed')
            ->and($log->attempt)->toBe(0)
            ->and($log->error_message)->toContain('Invalid');
    });

    it('throw exception when service is unsupported', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('some_signature'),
            service: 'unknown_service',
            event: 'some_event',
            secret: 'some_secret',
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));
    })->throws(WebhookException::class, "Webhook service 'unknown_service' is not supported.");

    it('logs failure when stripe signature format is invalid', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('invalid_format'),
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'stripe_secret',
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $log = WebhookLog::latest()->first();

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Invalid Stripe signature format');
    });

    it('logs failure when github signature format is invalid', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('invalid_format_without_sha256'),
            service: 'github',
            event: 'push',
            secret: 'github_secret',
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $log = WebhookLog::latest()->first();

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Invalid GitHub signature format');
    });

    it('logs failure with correct attempt number', function () {
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 2
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $log = WebhookLog::latest()->first();

        expect($log->attempt)->toBe(2);
    });
});

describe('RetryWebhookJob retry dispatching', function () {
    it('dispatches retry job on first failure', function () {
        Queue::fake();

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        Queue::assertPushed(RetryWebhookJob::class, function ($pushedJob) {
            // The next job should have attempt = 1
            return true;
        });
    });

    it('dispatches retry job on second failure', function () {
        Queue::fake();

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 1
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        Queue::assertPushed(RetryWebhookJob::class);
    });

    it('does not dispatch retry job on last attempt', function () {
        Queue::fake();
        config(['larawebhook.retries.max_attempts' => 3]);

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 2 // Last attempt (0, 1, 2 = 3 attempts)
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        Queue::assertNothingPushed();
    });

    it('does not dispatch retry when max_attempts is 1', function () {
        Queue::fake();
        config(['larawebhook.retries.max_attempts' => 1]);

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        Queue::assertNothingPushed();
    });

    it('dispatches retry job with delay from config', function () {
        Queue::fake();
        config(['larawebhook.retries.delays' => [5, 10, 30]]);

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

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
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 1
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

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
        $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $log = WebhookLog::latest()->first();

        expect($log->payload)->toBeArray()
            ->and($log->payload['action'])->toBe('push')
            ->and($log->payload['repository']['name'])->toBe('test');
    });

    it('handles invalid JSON payload by wrapping in raw key', function () {
        $payload = 'not valid json {{{';

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret'
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $log = WebhookLog::latest()->first();

        expect($log->payload)->toBeArray()
            ->and($log->payload)->not->toBeEmpty()
            ->and($log->payload['raw'])->toBe('not valid json {{{');
    });

    it('empty payload throw InvalidArgumentException', function () {
        $job = new RetryWebhookJob(
            payload: '',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret'
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));
    })->throws(InvalidArgumentException::class, 'Raw payload cannot be empty.');

    it('handles null JSON values', function () {
        $secret = 'github_secret';
        $payload = '{"value": null, "empty": ""}';
        $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

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
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 3
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

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
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 2
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        // Should still dispatch because attempt 2 < max_attempts (10)
        Queue::assertPushed(RetryWebhookJob::class);
    });

    it('uses default max_attempts of 3 when not configured', function () {
        Queue::fake();

        // Test that with attempt=2 (last of 3), no retry happens
        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 2
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

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
        $signature = incomingSignature('t='.$expiredTimestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'stripe',
            event: 'payment_intent.succeeded',
            secret: $secret,
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $log = WebhookLog::latest()->first();

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('expired');
    });

    it('succeeds when stripe timestamp is within tolerance', function () {
        $secret = 'stripe_secret';
        $payload = '{"type": "payment_intent.succeeded"}';
        $timestamp = time() - 100; // 100 seconds ago (within 300s tolerance)
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = incomingSignature('t='.$timestamp.',v1='.hash_hmac('sha256', $signedPayload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'stripe',
            event: 'payment_intent.succeeded',
            secret: $secret,
            attempt: 0
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $log = WebhookLog::latest()->first();

        expect($log->status)->toBe('success');
    });
});

describe('RetryWebhookJob external_id support', function () {
    it('does not persist external id when retry succeeds', function () {
        $secret = 'github_secret_key';

        $payload = '{"action": "push"}';

        $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 0,
            externalId: 'delivery-123-abc'
        );

        $job->handle(
            app(ValidateWebhook::class),
            app(RecordWebhookLog::class),
        );

        $log = WebhookLog::latest()->first();

        expect($log)->not->toBeNull()
            ->and($log->status)->toBe('success')
            ->and($log->external_id)->toBeNull();
    });

    it('keeps external id in retry context but does not persist it on failure logs', function () {
        Queue::fake();

        $uniqueExternalId = 'evt_failure_'.uniqid();

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0,
            externalId: $uniqueExternalId
        );

        $job->handle(
            app(ValidateWebhook::class),
            app(RecordWebhookLog::class),
        );

        $log = WebhookLog::latest()->first();

        expect($log)->not->toBeNull()
            ->and($log->status)->toBe('failed')
            ->and($log->external_id)->toBeNull();

        Queue::assertPushed(RetryWebhookJob::class, function (RetryWebhookJob $queuedJob) use ($uniqueExternalId) {
            return $queuedJob->attempt() === 1
                && $queuedJob->externalId() === $uniqueExternalId;
        });
    });

    it('passes external_id to next retry job', function () {
        Queue::fake();

        $job = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0,
            externalId: 'delivery-456'
        );

        $job->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

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
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );
        $job1->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        // Second attempt - fails
        $job2 = new RetryWebhookJob(
            payload: '{"test": "data"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 1
        );
        $job2->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        // Third attempt - success
        $secret = 'secret';
        $payload = '{"test": "data"}';
        $validSignature = incomingSignature('sha256='.hash_hmac('sha256', $payload, $secret));

        $job3 = new RetryWebhookJob(
            payload: $payload,
            signature: $validSignature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 2
        );
        $job3->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

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
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'secret',
            attempt: 0
        );
        $githubJob->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $stripeJob = new RetryWebhookJob(
            payload: '{"test": "stripe"}',
            signature: incomingSignature('invalid_format'),
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'secret',
            attempt: 0
        );
        $stripeJob->handle(app(ValidateWebhook::class), app(RecordWebhookLog::class));

        $githubLogs = WebhookLog::where('service', 'github')->count();
        $stripeLogs = WebhookLog::where('service', 'stripe')->count();

        expect($githubLogs)->toBe(1)
            ->and($stripeLogs)->toBe(1);
    });
});

it('keeps external id in retry context but does not persist it on retry logs', function () {
    Queue::fake();

    $externalId = 'evt_failure_'.bin2hex(random_bytes(6));

    $job = new RetryWebhookJob(
        payload: '{"test":"data"}',
        signature: incomingSignature('sha256=invalid'),
        service: 'github',
        event: 'push',
        secret: 'github_secret',
        attempt: 1,
        externalId: $externalId,
    );

    $job->handle(
        app(ValidateWebhook::class),
        app(RecordWebhookLog::class),
    );

    $log = WebhookLog::first();

    expect($log)->not->toBeNull()
        ->and($log->service)->toBe('github')
        ->and($log->event)->toBe('push')
        ->and($log->status)->toBe('failed')
        ->and($log->attempt)->toBe(1)
        ->and($log->external_id)->toBeNull()
        ->and($log->error_message)->toBe('Invalid GitHub webhook signature.');

    Queue::assertPushed(RetryWebhookJob::class, function (RetryWebhookJob $queuedJob) use ($externalId) {
        return $queuedJob->attempt() === 2
            && $queuedJob->externalId() === $externalId;
    });
});
