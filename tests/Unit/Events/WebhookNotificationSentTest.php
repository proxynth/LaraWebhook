<?php

declare(strict_types=1);

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Proxynth\Larawebhook\Events\WebhookNotificationSent;
use Proxynth\Larawebhook\Models\WebhookLog;

describe('WebhookNotificationSent', function () {
    it('uses Dispatchable trait', function () {
        $traits = class_uses(WebhookNotificationSent::class);

        expect($traits)->toContain(Dispatchable::class);
    });

    it('uses SerializesModels trait', function () {
        $traits = class_uses(WebhookNotificationSent::class);

        expect($traits)->toContain(SerializesModels::class);
    });

    it('can be instantiated with log and failure count', function () {
        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
            'error_message' => 'Card declined',
        ]);

        $event = new WebhookNotificationSent($log, 5);

        expect($event->log)->toBe($log)
            ->and($event->failureCount)->toBe(5);
    });

    it('has readonly properties', function () {
        $reflection = new ReflectionClass(WebhookNotificationSent::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        expect($params[0]->getName())->toBe('log')
            ->and($params[1]->getName())->toBe('failureCount');
    });
});

describe('WebhookNotificationSent dispatch', function () {
    beforeEach(function () {
        Event::fake();
    });

    it('can be dispatched', function () {
        $log = WebhookLog::create([
            'service' => 'github',
            'event' => 'push',
            'status' => 'failed',
            'payload' => [],
        ]);

        WebhookNotificationSent::dispatch($log, 3);

        Event::assertDispatched(WebhookNotificationSent::class);
    });

    it('dispatches with correct data', function () {
        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'charge.failed',
            'status' => 'failed',
            'payload' => ['id' => 'ch_123'],
        ]);

        WebhookNotificationSent::dispatch($log, 7);

        Event::assertDispatched(WebhookNotificationSent::class, function ($event) use ($log) {
            return $event->log->id === $log->id
                && $event->failureCount === 7;
        });
    });

    it('can be dispatched multiple times', function () {
        $log1 = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'test1',
            'status' => 'failed',
            'payload' => [],
        ]);

        $log2 = WebhookLog::create([
            'service' => 'github',
            'event' => 'test2',
            'status' => 'failed',
            'payload' => [],
        ]);

        WebhookNotificationSent::dispatch($log1, 3);
        WebhookNotificationSent::dispatch($log2, 5);

        Event::assertDispatchedTimes(WebhookNotificationSent::class, 2);
    });
});

describe('WebhookNotificationSent usage patterns', function () {
    it('provides access to log service', function () {
        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'payment.failed',
            'status' => 'failed',
            'payload' => [],
        ]);

        $event = new WebhookNotificationSent($log, 3);

        expect($event->log->service)->toBe('stripe');
    });

    it('provides access to log event', function () {
        $log = WebhookLog::create([
            'service' => 'github',
            'event' => 'push.main',
            'status' => 'failed',
            'payload' => [],
        ]);

        $event = new WebhookNotificationSent($log, 2);

        expect($event->log->event)->toBe('push.main');
    });

    it('provides access to log error message', function () {
        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'test',
            'status' => 'failed',
            'payload' => [],
            'error_message' => 'Invalid signature',
        ]);

        $event = new WebhookNotificationSent($log, 4);

        expect($event->log->error_message)->toBe('Invalid signature');
    });

    it('allows failure count of zero', function () {
        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'test',
            'status' => 'failed',
            'payload' => [],
        ]);

        $event = new WebhookNotificationSent($log, 0);

        expect($event->failureCount)->toBe(0);
    });

    it('allows high failure counts', function () {
        $log = WebhookLog::create([
            'service' => 'stripe',
            'event' => 'test',
            'status' => 'failed',
            'payload' => [],
        ]);

        $event = new WebhookNotificationSent($log, 9999);

        expect($event->failureCount)->toBe(9999);
    });
});
