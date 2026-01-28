<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Proxynth\Larawebhook\Notifications\Channels\SlackWebhookChannel;

beforeEach(function () {
    Log::spy();
});

describe('SlackWebhookChannel class structure', function () {
    it('accepts HttpClient in constructor', function () {
        $http = new HttpClient;
        $channel = new SlackWebhookChannel($http);

        expect($channel)->toBeInstanceOf(SlackWebhookChannel::class);
    });

    it('has a send method', function () {
        expect(method_exists(SlackWebhookChannel::class, 'send'))->toBeTrue();
    });

    it('send method has correct signature', function () {
        $reflection = new ReflectionMethod(SlackWebhookChannel::class, 'send');
        $parameters = $reflection->getParameters();

        expect($parameters)->toHaveCount(2)
            ->and($parameters[0]->getName())->toBe('notifiable')
            ->and($parameters[1]->getName())->toBe('notification')
            ->and($parameters[1]->getType()?->getName())->toBe(Notification::class);
    });

    it('send method returns void', function () {
        $reflection = new ReflectionMethod(SlackWebhookChannel::class, 'send');

        expect($reflection->getReturnType()?->getName())->toBe('void');
    });
});

describe('SlackWebhookChannel webhook URL handling', function () {
    it('returns early when webhook URL is empty', function () {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldNotReceive('post');

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): ?string
            {
                return null;
            }
        };

        $notification = Mockery::mock(Notification::class);

        $channel->send($notifiable, $notification);

        // If we get here without exception, the test passes
        expect(true)->toBeTrue();
    });

    it('returns early when webhook URL is empty string', function () {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldNotReceive('post');

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return '';
            }
        };

        $notification = Mockery::mock(Notification::class);

        $channel->send($notifiable, $notification);

        expect(true)->toBeTrue();
    });
});

describe('SlackWebhookChannel toSlack method handling', function () {
    it('returns early when notification has no toSlack method', function () {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldNotReceive('post');

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        // Use a notification without toSlack method
        $notification = new class extends Notification
        {
            // No toSlack method
        };

        $channel->send($notifiable, $notification);

        expect(true)->toBeTrue();
    });

    it('returns early when toSlack returns non-array', function () {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldNotReceive('post');

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): string
            {
                return 'not an array';
            }
        };

        $channel->send($notifiable, $notification);

        expect(true)->toBeTrue();
    });

    it('returns early when toSlack returns null', function () {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldNotReceive('post');

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): ?array
            {
                return null;
            }
        };

        $channel->send($notifiable, $notification);

        expect(true)->toBeTrue();
    });
});

describe('SlackWebhookChannel successful sending', function () {
    it('sends POST request to webhook URL with payload', function () {
        $webhookUrl = 'https://hooks.slack.com/services/T00/B00/xxx';
        $payload = ['text' => 'Test message', 'channel' => '#general'];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->andReturn(true);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->with($webhookUrl, $payload)
            ->andReturn($response);

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class($webhookUrl)
        {
            public function __construct(private string $url) {}

            public function routeNotificationFor(string $channel, $notification): string
            {
                return $this->url;
            }
        };

        $notification = new class($payload) extends Notification
        {
            public function __construct(private array $payload) {}

            public function toSlack($notifiable): array
            {
                return $this->payload;
            }
        };

        $channel->send($notifiable, $notification);

        // Mockery assertions are verified automatically
        expect(true)->toBeTrue();
    });

    it('sends complex Slack payload with attachments', function () {
        $webhookUrl = 'https://hooks.slack.com/services/T00/B00/xxx';
        $payload = [
            'text' => 'Webhook Failure Alert',
            'attachments' => [
                [
                    'color' => 'danger',
                    'title' => 'Service: stripe',
                    'fields' => [
                        ['title' => 'Event', 'value' => 'payment.failed', 'short' => true],
                        ['title' => 'Count', 'value' => '3', 'short' => true],
                    ],
                ],
            ],
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->andReturn(true);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->with($webhookUrl, $payload)
            ->andReturn($response);

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class($webhookUrl)
        {
            public function __construct(private string $url) {}

            public function routeNotificationFor(string $channel, $notification): string
            {
                return $this->url;
            }
        };

        $notification = new class($payload) extends Notification
        {
            public function __construct(private array $payload) {}

            public function toSlack($notifiable): array
            {
                return $this->payload;
            }
        };

        $channel->send($notifiable, $notification);

        expect(true)->toBeTrue();
    });

    it('does not log anything on successful request', function () {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->andReturn(true);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->andReturn($response);

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return ['text' => 'Test'];
            }
        };

        $channel->send($notifiable, $notification);

        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    });
});

describe('SlackWebhookChannel failed HTTP response', function () {
    it('logs warning when response is not successful', function () {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->andReturn(false);
        $response->shouldReceive('status')->andReturn(400);
        $response->shouldReceive('body')->andReturn('Bad Request');

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->andReturn($response);

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return ['text' => 'Test'];
            }
        };

        $channel->send($notifiable, $notification);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Larawebhook: Failed to send Slack notification', Mockery::on(function ($context) {
                return $context['status'] === 400 && $context['body'] === 'Bad Request';
            }));
    });

    it('logs warning with correct context for 500 error', function () {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->andReturn(false);
        $response->shouldReceive('status')->andReturn(500);
        $response->shouldReceive('body')->andReturn('Internal Server Error');

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->andReturn($response);

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return ['text' => 'Test'];
            }
        };

        $channel->send($notifiable, $notification);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Larawebhook: Failed to send Slack notification', Mockery::on(function ($context) {
                return $context['status'] === 500;
            }));
    });

    it('logs warning for invalid webhook URL response', function () {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->andReturn(false);
        $response->shouldReceive('status')->andReturn(404);
        $response->shouldReceive('body')->andReturn('Webhook not found');

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->andReturn($response);

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/invalid';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return ['text' => 'Test'];
            }
        };

        $channel->send($notifiable, $notification);

        Log::shouldHaveReceived('warning')->once();
    });
});

describe('SlackWebhookChannel exception handling', function () {
    it('logs error when HTTP request throws exception', function () {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->andThrow(new Exception('Connection timeout'));

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return ['text' => 'Test'];
            }
        };

        $channel->send($notifiable, $notification);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Larawebhook: Exception sending Slack notification', Mockery::on(function ($context) {
                return $context['message'] === 'Connection timeout';
            }));
    });

    it('does not throw exception to caller', function () {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->andThrow(new RuntimeException('Network error'));

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return ['text' => 'Test'];
            }
        };

        // This should not throw
        $channel->send($notifiable, $notification);

        expect(true)->toBeTrue();
    });

    it('handles SSL certificate errors gracefully', function () {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->andThrow(new Exception('SSL certificate problem'));

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return ['text' => 'Test'];
            }
        };

        $channel->send($notifiable, $notification);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Larawebhook: Exception sending Slack notification', Mockery::on(function ($context) {
                return str_contains($context['message'], 'SSL');
            }));
    });

    it('handles DNS resolution errors gracefully', function () {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->andThrow(new Exception('Could not resolve host'));

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return ['text' => 'Test'];
            }
        };

        $channel->send($notifiable, $notification);

        Log::shouldHaveReceived('error')->once();
    });
});

describe('SlackWebhookChannel integration scenarios', function () {
    it('works with AnonymousNotifiable', function () {
        $webhookUrl = 'https://hooks.slack.com/services/xxx';

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->andReturn(true);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->with($webhookUrl, ['text' => 'Test'])
            ->andReturn($response);

        $channel = new SlackWebhookChannel($http);

        // Simulate AnonymousNotifiable behavior
        $notifiable = new class($webhookUrl)
        {
            private array $routes = [];

            public function __construct(string $slackUrl)
            {
                $this->routes['slack'] = $slackUrl;
            }

            public function routeNotificationFor(string $channel, $notification): ?string
            {
                return $this->routes[$channel] ?? null;
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return ['text' => 'Test'];
            }
        };

        $channel->send($notifiable, $notification);

        expect(true)->toBeTrue();
    });

    it('handles empty array payload', function () {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->andReturn(true);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->with('https://hooks.slack.com/services/xxx', [])
            ->andReturn($response);

        $channel = new SlackWebhookChannel($http);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel, $notification): string
            {
                return 'https://hooks.slack.com/services/xxx';
            }
        };

        $notification = new class extends Notification
        {
            public function toSlack($notifiable): array
            {
                return [];
            }
        };

        $channel->send($notifiable, $notification);

        expect(true)->toBeTrue();
    });
});
