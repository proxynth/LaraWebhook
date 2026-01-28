<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Notification;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Facades\Larawebhook;
use Proxynth\Larawebhook\Larawebhook as LarawebhookClass;
use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Services\FailureDetector;

describe('Larawebhook Facade', function () {
    it('extends the base Facade class', function () {
        expect(Larawebhook::class)->toExtend(Facade::class);
    });

    it('returns the correct facade accessor', function () {
        // Use reflection to test the protected method
        $reflection = new ReflectionClass(Larawebhook::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $accessor = $method->invoke(null);

        expect($accessor)->toBe(LarawebhookClass::class);
    });

    it('resolves to the Larawebhook class instance', function () {
        $resolved = Larawebhook::getFacadeRoot();

        expect($resolved)->toBeInstanceOf(LarawebhookClass::class);
    });

    it('resolves the same instance on multiple calls (singleton behavior)', function () {
        $first = Larawebhook::getFacadeRoot();
        $second = Larawebhook::getFacadeRoot();

        expect($first)->toBe($second);
    });

    it('can be resolved from the container using the class name', function () {
        $resolved = app(LarawebhookClass::class);

        expect($resolved)->toBeInstanceOf(LarawebhookClass::class);
    });

    it('facade root and container resolution are same type', function () {
        $facadeInstance = Larawebhook::getFacadeRoot();
        $containerInstance = app(LarawebhookClass::class);

        // Both should be instances of the same class
        expect($facadeInstance)->toBeInstanceOf(LarawebhookClass::class)
            ->and($containerInstance)->toBeInstanceOf(LarawebhookClass::class)
            ->and(get_class($facadeInstance))->toBe(get_class($containerInstance));
    });
});

describe('Larawebhook Facade mocking', function () {
    it('can be faked for testing', function () {
        Larawebhook::shouldReceive('someMethod')
            ->once()
            ->andReturn('mocked result');

        $result = Larawebhook::someMethod();

        expect($result)->toBe('mocked result');
    });

    it('can be partially mocked', function () {
        Larawebhook::partialMock()
            ->shouldReceive('customMethod')
            ->andReturn('partial mock result');

        $result = Larawebhook::customMethod();

        expect($result)->toBe('partial mock result');
    });

    it('can spy on facade calls', function () {
        $spy = Larawebhook::spy();

        // Call some method (will return null since it's a spy)
        Larawebhook::testMethod('arg1', 'arg2');

        $spy->shouldHaveReceived('testMethod')->with('arg1', 'arg2');
    });
});

describe('Larawebhook Facade integration', function () {
    beforeEach(function () {
        // Clear any previous mocks
        Larawebhook::clearResolvedInstances();
    });

    it('provides access to the underlying class', function () {
        $instance = Larawebhook::getFacadeRoot();

        // The underlying class should be the Larawebhook class
        expect(get_class($instance))->toBe(LarawebhookClass::class);
    });

    it('can check if facade is resolved', function () {
        // First clear instances
        Larawebhook::clearResolvedInstances();

        // Force resolution
        Larawebhook::getFacadeRoot();

        // Now it should be resolved
        $reflection = new ReflectionClass(Facade::class);
        $property = $reflection->getProperty('resolvedInstance');
        $property->setAccessible(true);

        $instances = $property->getValue();

        expect($instances)->toHaveKey(LarawebhookClass::class);
    });
});

describe('Larawebhook Facade methods - Validation', function () {
    beforeEach(function () {
        Larawebhook::clearResolvedInstances();
        config([
            'larawebhook.services.stripe.webhook_secret' => 'test_stripe_secret',
            'larawebhook.services.github.webhook_secret' => 'test_github_secret',
            'larawebhook.notifications.enabled' => false,
            'larawebhook.retries.enabled' => true,
            'larawebhook.retries.max_attempts' => 3,
            'larawebhook.retries.delays' => [0, 0, 0],
        ]);
    });

    it('validate() works via facade', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_github_secret');

        $result = Larawebhook::validate($payload, $signature, 'github');

        expect($result)->toBeTrue();
    });

    it('validateAndLog() works via facade', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_github_secret');

        $log = Larawebhook::validateAndLog($payload, $signature, 'github', 'push');

        expect($log)->toBeInstanceOf(WebhookLog::class)
            ->and($log->status)->toBe('success');
    });

    it('validateWithRetries() works via facade', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_github_secret');

        $log = Larawebhook::validateWithRetries($payload, $signature, 'github', 'push');

        expect($log)->toBeInstanceOf(WebhookLog::class)
            ->and($log->status)->toBe('success');
    });
});

describe('Larawebhook Facade methods - Logging', function () {
    beforeEach(function () {
        Larawebhook::clearResolvedInstances();
        config(['larawebhook.notifications.enabled' => false]);
    });

    it('logSuccess() works via facade', function () {
        $log = Larawebhook::logSuccess('stripe', 'payment.succeeded', ['amount' => 1000]);

        expect($log)->toBeInstanceOf(WebhookLog::class)
            ->and($log->status)->toBe('success')
            ->and($log->service)->toBe('stripe');
    });

    it('logFailure() works via facade', function () {
        $log = Larawebhook::logFailure('stripe', 'payment.failed', ['id' => '123'], 'Card declined');

        expect($log)->toBeInstanceOf(WebhookLog::class)
            ->and($log->status)->toBe('failed')
            ->and($log->error_message)->toBe('Card declined');
    });
});

describe('Larawebhook Facade methods - Queries', function () {
    beforeEach(function () {
        Larawebhook::clearResolvedInstances();
        WebhookLog::query()->delete();

        WebhookLog::create(['service' => 'stripe', 'event' => 'test', 'status' => 'success', 'payload' => []]);
        WebhookLog::create(['service' => 'stripe', 'event' => 'test', 'status' => 'failed', 'payload' => [], 'error_message' => 'Error']);
        WebhookLog::create(['service' => 'github', 'event' => 'test', 'status' => 'success', 'payload' => []]);
    });

    it('logs() returns all logs', function () {
        $logs = Larawebhook::logs();

        expect($logs)->toBeInstanceOf(Collection::class)
            ->and($logs)->toHaveCount(3);
    });

    it('logsForService() filters by service', function () {
        $logs = Larawebhook::logsForService('stripe');

        expect($logs)->toHaveCount(2)
            ->and($logs->every(fn ($log) => $log->service === 'stripe'))->toBeTrue();
    });

    it('failedLogs() returns only failed', function () {
        $logs = Larawebhook::failedLogs();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->status)->toBe('failed');
    });

    it('successfulLogs() returns only successful', function () {
        $logs = Larawebhook::successfulLogs();

        expect($logs)->toHaveCount(2)
            ->and($logs->every(fn ($log) => $log->status === 'success'))->toBeTrue();
    });
});

describe('Larawebhook Facade methods - Notifications', function () {
    beforeEach(function () {
        Larawebhook::clearResolvedInstances();
        Notification::fake();
        WebhookLog::query()->delete();
        app(FailureDetector::class)->clearCooldown('stripe', 'payment.failed');
    });

    it('notificationsEnabled() returns config value', function () {
        config(['larawebhook.notifications.enabled' => true]);
        expect(Larawebhook::notificationsEnabled())->toBeTrue();

        config(['larawebhook.notifications.enabled' => false]);
        expect(Larawebhook::notificationsEnabled())->toBeFalse();
    });

    it('getNotificationChannels() returns configured channels', function () {
        config(['larawebhook.notifications.channels' => ['mail', 'slack']]);

        expect(Larawebhook::getNotificationChannels())->toBe(['mail', 'slack']);
    });

    it('canSendNotification() checks cooldown', function () {
        expect(Larawebhook::canSendNotification('stripe', 'payment.failed'))->toBeTrue();
    });

    it('clearCooldown() resets notification cooldown', function () {
        app(FailureDetector::class)->markNotificationSent('stripe', 'payment.failed');
        expect(Larawebhook::canSendNotification('stripe', 'payment.failed'))->toBeFalse();

        Larawebhook::clearCooldown('stripe', 'payment.failed');
        expect(Larawebhook::canSendNotification('stripe', 'payment.failed'))->toBeTrue();
    });

    it('getFailureCount() returns failure count', function () {
        config(['larawebhook.notifications.failure_window_minutes' => 30]);

        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => [],
            ]);
        }

        expect(Larawebhook::getFailureCount('stripe', 'payment.failed'))->toBe(3);
    });

    it('sendNotificationIfNeeded() sends when threshold reached', function () {
        config([
            'larawebhook.notifications.enabled' => true,
            'larawebhook.notifications.failure_threshold' => 3,
        ]);

        for ($i = 0; $i < 3; $i++) {
            WebhookLog::create([
                'service' => 'stripe',
                'event' => 'payment.failed',
                'status' => 'failed',
                'payload' => [],
            ]);
        }

        $result = Larawebhook::sendNotificationIfNeeded('stripe', 'payment.failed');

        expect($result)->toBeTrue();
    });
});

describe('Larawebhook Facade methods - Configuration', function () {
    beforeEach(function () {
        Larawebhook::clearResolvedInstances();
        config([
            'larawebhook.services.stripe.webhook_secret' => 'stripe_secret',
            'larawebhook.services.github.webhook_secret' => 'github_secret',
        ]);
    });

    it('getSecret() returns service secret', function () {
        expect(Larawebhook::getSecret('stripe'))->toBe('stripe_secret')
            ->and(Larawebhook::getSecret('github'))->toBe('github_secret')
            ->and(Larawebhook::getSecret('unknown'))->toBeNull();
    });

    it('isServiceSupported() checks service support', function () {
        expect(Larawebhook::isServiceSupported('stripe'))->toBeTrue()
            ->and(Larawebhook::isServiceSupported('github'))->toBeTrue()
            ->and(Larawebhook::isServiceSupported('unknown'))->toBeFalse();
    });

    it('supportedServices() returns all service names', function () {
        expect(Larawebhook::supportedServices())->toBe(['stripe', 'github']);
    });

    it('services() returns all enum cases', function () {
        $services = Larawebhook::services();

        expect($services)->toBeArray()
            ->and($services)->toContain(WebhookService::Stripe)
            ->and($services)->toContain(WebhookService::Github);
    });

    it('service() converts string to enum', function () {
        expect(Larawebhook::service('stripe'))->toBe(WebhookService::Stripe)
            ->and(Larawebhook::service('github'))->toBe(WebhookService::Github)
            ->and(Larawebhook::service('unknown'))->toBeNull();
    });
});

describe('Larawebhook Facade with WebhookService enum', function () {
    beforeEach(function () {
        Larawebhook::clearResolvedInstances();
        config([
            'larawebhook.services.stripe.webhook_secret' => 'test_stripe_secret',
            'larawebhook.services.github.webhook_secret' => 'test_github_secret',
            'larawebhook.notifications.enabled' => false,
        ]);
    });

    it('validate() accepts enum', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_github_secret');

        $result = Larawebhook::validate($payload, $signature, WebhookService::Github);

        expect($result)->toBeTrue();
    });

    it('validateAndLog() accepts enum', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_github_secret');

        $log = Larawebhook::validateAndLog($payload, $signature, WebhookService::Github, 'push');

        expect($log->service)->toBe('github');
    });

    it('logsForService() accepts enum', function () {
        WebhookLog::query()->delete();
        WebhookLog::create(['service' => 'stripe', 'event' => 'test', 'status' => 'success', 'payload' => []]);

        $logs = Larawebhook::logsForService(WebhookService::Stripe);

        expect($logs)->toHaveCount(1);
    });

    it('getSecret() accepts enum', function () {
        expect(Larawebhook::getSecret(WebhookService::Stripe))->toBe('test_stripe_secret');
    });
});
