<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Proxynth\Larawebhook\LarawebhookServiceProvider;
use Proxynth\Larawebhook\Notifications\Channels\SlackWebhookChannel;
use Proxynth\Larawebhook\Services\FailureDetector;
use Proxynth\Larawebhook\Services\NotificationSender;
use Proxynth\Larawebhook\Services\WebhookLogger;

describe('LarawebhookServiceProvider service registration', function () {
    it('registers FailureDetector as singleton', function () {
        $instance1 = app(FailureDetector::class);
        $instance2 = app(FailureDetector::class);

        expect($instance1)->toBeInstanceOf(FailureDetector::class)
            ->and($instance1)->toBe($instance2);
    });

    it('registers NotificationSender as singleton', function () {
        $instance1 = app(NotificationSender::class);
        $instance2 = app(NotificationSender::class);

        expect($instance1)->toBeInstanceOf(NotificationSender::class)
            ->and($instance1)->toBe($instance2);
    });

    it('registers WebhookLogger as singleton', function () {
        $instance1 = app(WebhookLogger::class);
        $instance2 = app(WebhookLogger::class);

        expect($instance1)->toBeInstanceOf(WebhookLogger::class)
            ->and($instance1)->toBe($instance2);
    });

    it('injects dependencies correctly into NotificationSender', function () {
        $sender = app(NotificationSender::class);

        // Use reflection to check dependencies
        $reflection = new ReflectionClass($sender);
        $failureDetectorProperty = $reflection->getProperty('failureDetector');
        $failureDetectorProperty->setAccessible(true);

        expect($failureDetectorProperty->getValue($sender))->toBeInstanceOf(FailureDetector::class);
    });

    it('injects dependencies correctly into WebhookLogger', function () {
        $logger = app(WebhookLogger::class);

        // Use reflection to check dependencies
        $reflection = new ReflectionClass($logger);
        $notificationSenderProperty = $reflection->getProperty('notificationSender');
        $notificationSenderProperty->setAccessible(true);

        expect($notificationSenderProperty->getValue($logger))->toBeInstanceOf(NotificationSender::class);
    });
});

describe('LarawebhookServiceProvider Slack channel registration', function () {
    it('extends notification channel manager with slack channel', function () {
        // Get the channel manager
        $channelManager = app(ChannelManager::class);

        // The slack driver should be registered
        // We can verify by checking if we can create the driver
        $driver = $channelManager->driver('slack');

        expect($driver)->toBeInstanceOf(SlackWebhookChannel::class);
    });

    it('creates SlackWebhookChannel with HttpClient dependency', function () {
        $channelManager = app(ChannelManager::class);
        $driver = $channelManager->driver('slack');

        // Use reflection to verify HttpClient is injected
        $reflection = new ReflectionClass($driver);
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);

        expect($httpProperty->getValue($driver))->toBeInstanceOf(HttpClient::class);
    });

    it('slack channel can send notifications via HTTP', function () {
        Http::fake([
            'hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        config([
            'larawebhook.notifications.enabled' => true,
            'larawebhook.notifications.channels' => ['slack'],
            'larawebhook.notifications.slack_webhook' => 'https://hooks.slack.com/services/xxx/yyy/zzz',
        ]);

        // Create a simple notification that uses slack
        $notification = new class extends \Illuminate\Notifications\Notification
        {
            public function via($notifiable): array
            {
                return ['slack'];
            }

            public function toSlack($notifiable): array
            {
                return ['text' => 'Test notification'];
            }
        };

        // Send via on-demand notification
        Notification::route('slack', 'https://hooks.slack.com/services/xxx/yyy/zzz')
            ->notify($notification);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.com/services/xxx/yyy/zzz'
                && $request['text'] === 'Test notification';
        });
    });
});

describe('LarawebhookServiceProvider middleware registration', function () {
    it('registers validate-webhook middleware alias', function () {
        $router = app(\Illuminate\Routing\Router::class);

        // Get middleware aliases
        $middlewareAliases = $router->getMiddleware();

        expect($middlewareAliases)->toHaveKey('validate-webhook');
    });
});

describe('LarawebhookServiceProvider configuration', function () {
    it('loads config file', function () {
        expect(config('larawebhook'))->toBeArray()
            ->and(config('larawebhook'))->toHaveKey('services');
    });

    it('loads views', function () {
        $viewFinder = app('view')->getFinder();
        $hints = $viewFinder->getHints();

        expect($hints)->toHaveKey('larawebhook');
    });
});

describe('LarawebhookServiceProvider package info', function () {
    it('is registered as a service provider', function () {
        $providers = app()->getLoadedProviders();

        expect($providers)->toHaveKey(LarawebhookServiceProvider::class);
    });
});
