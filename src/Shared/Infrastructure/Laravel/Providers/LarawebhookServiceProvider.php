<?php

namespace Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Notification;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Console\PruneWebhookLogsCommand;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\Channels\SlackWebhookChannel;
use Proxynth\Larawebhook\Contracts\IdempotencyResolver;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Laravel\Middleware\ValidateWebhook;
use Proxynth\Larawebhook\Services\DefaultIdempotencyResolver;
use Proxynth\Larawebhook\Services\FailureDetector;
use Proxynth\Larawebhook\Services\NotificationSender;
use Proxynth\Larawebhook\Services\PayloadStorageResolver;
use Proxynth\Larawebhook\Services\WebhookLogger;
use Proxynth\Larawebhook\Services\WebhookValidatorFactory;
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
        $router->aliasMiddleware('validate-webhook', ValidateWebhook::class);

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
            ])->hasCommands(PruneWebhookLogsCommand::class)
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
