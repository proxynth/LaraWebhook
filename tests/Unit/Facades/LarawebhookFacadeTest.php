<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Proxynth\Larawebhook\Facades\Larawebhook;
use Proxynth\Larawebhook\Larawebhook as LarawebhookClass;

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
