<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Queue;
use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Processing\Application\Data\RetryPolicy;
use Proxynth\Larawebhook\Processing\Application\Ports\RetryPolicyResolver;
use Proxynth\Larawebhook\Processing\Application\UseCases\RetryWebhook;
use Proxynth\Larawebhook\Processing\Domain\Events\WebhookProcessingFailed;
use Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Jobs\RetryWebhookJob;
use Proxynth\Larawebhook\Shared\Application\Ports\EventBus;
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\EventBus\LaravelEventBus;
use Proxynth\Larawebhook\Tests\Fakes\Processing\FakeRetryPolicyResolver;

beforeEach(function () {
    config()->set('larawebhook.retries.enabled', true);
    config()->set('larawebhook.services.github.webhook_secret', 'github_secret_key');
    config()->set('larawebhook.services.stripe.webhook_secret', 'secret');
    app()->instance(
        RetryPolicyResolver::class,
        new FakeRetryPolicyResolver(new RetryPolicy(
            maxAttempts: 3,
            delays: [1, 5, 10],
        )),
    );
});

describe('RetryWebhookJob structure', function () {
    it('implements ShouldQueue and keeps serialized input', function () {
        $job = new RetryWebhookJob(
            payload: '{"test":"data"}',
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
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

        expect(serialize($job))->not->toContain('secret');
    });

    it('keeps the unique id stable for the same payload', function () {
        $job1 = new RetryWebhookJob(
            payload: '{"test":"data"}',
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
        );

        $job2 = new RetryWebhookJob(
            payload: '{"test":"data"}',
            signature: incomingSignature('test_signature'),
            service: 'stripe',
            event: 'payment.succeeded',
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
            attempt: 0,
            externalId: 'delivery_123',
            idempotencyKey: 'delivery_123',
        );

        $job->handle(app(RetryWebhook::class), app(EventBus::class));

        Queue::assertNothingPushed();
    });

    it('dispatches the next retry when validation fails and attempts remain', function () {
        Queue::fake();

        $job = new RetryWebhookJob(
            payload: '{"action":"push"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            attempt: 0,
            externalId: 'delivery_123',
            idempotencyKey: 'delivery_123',
        );

        $job->handle(app(RetryWebhook::class), app(EventBus::class));

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
        app()->instance(
            RetryPolicyResolver::class,
            new FakeRetryPolicyResolver(new RetryPolicy(
                maxAttempts: 1,
                delays: [1, 5, 10],
            )),
        );

        $job = new RetryWebhookJob(
            payload: '{"action":"push"}',
            signature: incomingSignature('sha256=invalid'),
            service: 'github',
            event: 'push',
            attempt: 0,
            externalId: 'delivery_123',
            idempotencyKey: 'delivery_123',
        );

        $job->handle(app(RetryWebhook::class), app(EventBus::class));

        Queue::assertNothingPushed();
    });
});

it('dispatches domain events produced by retry webhook', function () {
    Event::fake();

    app()->instance(
        EventBus::class,
        new LaravelEventBus(app(Dispatcher::class)),
    );

    $job = new RetryWebhookJob(
        payload: '{"action":"opened"}',
        signature: Signature::fromString('sha256=invalid'),
        service: 'github',
        event: 'opened',
        attempt: 1,
        externalId: 'delivery_123',
        idempotencyKey: 'delivery_123',
    );

    $job->handle(
        app(RetryWebhook::class),
        app(EventBus::class),
    );

    Event::assertDispatched(WebhookProcessingFailed::class);
    Event::assertDispatched(WebhookLogged::class);
});
