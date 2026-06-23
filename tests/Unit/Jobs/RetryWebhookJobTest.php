<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Proxynth\Larawebhook\Processing\Application\UseCases\RetryWebhook;
use Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Jobs\RetryWebhookJob;

beforeEach(function () {
    config([
        'larawebhook.retries.enabled' => true,
        'larawebhook.retries.max_attempts' => 3,
        'larawebhook.retries.delays' => [1, 5, 10],
    ]);
});

describe('RetryWebhookJob structure', function () {
    it('implements ShouldQueue and keeps serialized input', function () {
        $job = new RetryWebhookJob(
            payload: '{"test":"data"}',
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'secret',
            attempt: 2,
            externalId: 'delivery_123',
            idempotencyKey: 'dedupe_123',
        );

        expect($job)->toBeInstanceOf(ShouldQueue::class)
            ->and($job->payload())->toBe('{"test":"data"}')
            ->and($job->service())->toBe('stripe')
            ->and($job->event())->toBe('payment.succeeded')
            ->and($job->attempt())->toBe(2)
            ->and($job->externalId())->toBe('delivery_123')
            ->and($job->idempotencyKey())->toBe('dedupe_123');
    });

    it('keeps the unique id stable for the same payload', function () {
        $job1 = new RetryWebhookJob(
            payload: '{"test":"data"}',
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'secret',
        );

        $job2 = new RetryWebhookJob(
            payload: '{"test":"data"}',
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
            secret: 'secret',
        );

        expect($job1->uniqueId())->toBe($job2->uniqueId());
    });
});

describe('RetryWebhookJob dispatching', function () {
    it('does not dispatch a new job when validation succeeds', function () {
        Queue::fake();

        $secret = 'github_secret_key';
        $payload = '{"action":"push"}';
        $signature = incomingSignature('sha256='.hash_hmac('sha256', $payload, $secret));

        $job = new RetryWebhookJob(
            payload: $payload,
            signature: $signature,
            service: 'github',
            event: 'push',
            secret: $secret,
            attempt: 0,
            externalId: 'delivery_123',
            idempotencyKey: 'delivery_123',
        );

        $job->handle(app(RetryWebhook::class));

        Queue::assertNothingPushed();
    });

    it('dispatches the next retry when validation fails and attempts remain', function () {
        Queue::fake();

        $job = new RetryWebhookJob(
            payload: '{"action":"push"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'github_secret_key',
            attempt: 0,
            externalId: 'delivery_123',
            idempotencyKey: 'delivery_123',
        );

        $job->handle(app(RetryWebhook::class));

        Queue::assertPushed(RetryWebhookJob::class, function (RetryWebhookJob $queuedJob) {
            return $queuedJob->attempt() === 1
                && $queuedJob->service() === 'github'
                && $queuedJob->event() === 'push'
                && $queuedJob->externalId() === 'delivery_123'
                && $queuedJob->idempotencyKey() === 'delivery_123';
        });
    });

    it('does not dispatch when the retry limit is reached', function () {
        Queue::fake();
        config(['larawebhook.retries.max_attempts' => 1]);

        $job = new RetryWebhookJob(
            payload: '{"action":"push"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            secret: 'github_secret_key',
            attempt: 0,
            externalId: 'delivery_123',
            idempotencyKey: 'delivery_123',
        );

        $job->handle(app(RetryWebhook::class));

        Queue::assertNothingPushed();
    });
});
