<?php

namespace Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Notification;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogRepository;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Console\PruneWebhookLogsConsoleCommand;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\Channels\SlackWebhookChannel;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\FailureDetector;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\NotificationSender;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\EloquentWebhookLogRepository;
use Proxynth\Larawebhook\Audit\Infrastructure\Logging\WebhookLogger;
use Proxynth\Larawebhook\Audit\Infrastructure\Payload\PayloadStorageResolver;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ReceiveWebhook;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ValidateWebhook as ValidateWebhookUseCase;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Laravel\Middleware\ValidateWebhook as ValidateWebhookMiddleware;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Validation\WebhookValidatorFactory;
use Proxynth\Larawebhook\Processing\Application\Ports\IdempotencyResolver;
use Proxynth\Larawebhook\Processing\Application\UseCases\ReplayWebhook;
use Proxynth\Larawebhook\Processing\Infrastructure\Idempotency\DefaultIdempotencyResolver;
use Proxynth\Larawebhook\Shared\Application\Larawebhook;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LarawebhookServiceProvider extends PackageServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->mergeConfigFrom(
            $this->packagePath('config/larawebhook.php'),
            'larawebhook'
        );

        $this->publishesMigrations([
            $this->packagePath('database/migrations') => database_path('migrations'),
        ]);

        $this->registerApiRoutes();
        $this->registerDashboardRoutes();

        $this->loadViewsFrom($this->packagePath('resources/views'), 'larawebhook');

        $this->publishes([
            $this->packagePath('resources/views') => resource_path('views/vendor/larawebhook'),
        ], 'larawebhook-views');

        // Register middleware alias
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('validate-webhook', ValidateWebhookMiddleware::class);

        // Register custom Slack channel
        $this->registerSlackChannel();

        AboutCommand::add('Larawebhook', fn () => [
            'name' => 'Larawebhook',
            'Version' => '0.0.0',
        ]);
    }

    public function register(): void
    {
        parent::register();

        $this->registerServices();
    }

    public function configurePackage(Package $package): void
    {
        $package->name('larawebhook')
            ->hasMigrations([
                'create_webhook_logs_table',
            ])->hasCommands(PruneWebhookLogsConsoleCommand::class)
            ->hasViews();
    }

    /**
     * Register notification-related services.
     */
    private function registerServices(): void
    {
        // Register main Larawebhook class as singleton

        $this->app->singleton(Larawebhook::class);

        $this->app->alias(Larawebhook::class, 'larawebhook');
        $this->app->alias(Larawebhook::class, \Proxynth\Larawebhook\Larawebhook::class);

        // Register FailureDetector as singleton
        $this->app->singleton(FailureDetector::class, function () {
            return new FailureDetector;
        });

        // Register NotificationSender as singleton
        $this->app->singleton(NotificationSender::class, function ($app) {
            return new NotificationSender(
                $app->make(FailureDetector::class),
                $app->make(Dispatcher::class)
            );
        });

        // Register WebhookLogger as singleton with dependencies
        $this->app->singleton(WebhookLogger::class, function ($app) {
            return new WebhookLogger(
                $app->make(PayloadStorageResolver::class),
                $app->make(NotificationSender::class)
            );
        });

        // Register IdempotencyResolver as singleton with default implementation
        $this->app->singleton(IdempotencyResolver::class, DefaultIdempotencyResolver::class);

        // Register WebhookValidatorFactory as singleton
        $this->app->singleton(WebhookValidatorFactory::class);

        // Register ReplayWebhook use case
        $this->app->singleton(ReplayWebhook::class);

        // Register WebhookLogRepository persistence repository
        $this->app->singleton(
            WebhookLogRepository::class,
            EloquentWebhookLogRepository::class
        );

        $this->app->singleton(ValidateWebhookUseCase::class);
        $this->app->singleton(RecordWebhookLog::class);
        $this->app->singleton(ReceiveWebhook::class);
    }

    /**
     * Register the custom Slack webhook channel.
     */
    private function registerSlackChannel(): void
    {
        Notification::resolved(function (ChannelManager $service) {
            $service->extend('slack', function ($app) {
                return new SlackWebhookChannel($app->make(HttpClient::class));
            });
        });
    }

    /**
     * Register dashboard route.
     */
    private function registerDashboardRoutes(): void
    {
        if (! config('larawebhook.dashboard.enabled', false)) {
            return;
        }

        $this->loadRoutesFrom($this->packagePath('routes/web.php'));
    }

    /**
     * Register API routes.
     */
    private function registerApiRoutes(): void
    {
        if (! config('larawebhook.api.enabled', false)) {
            return;
        }

        $this->loadRoutesFrom($this->packagePath('routes/api.php'));
    }

    private function packagePath(string $path = ''): string
    {
        return dirname(__DIR__, 5).($path !== '' ? DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}
