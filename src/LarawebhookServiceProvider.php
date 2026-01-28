<?php

namespace Proxynth\Larawebhook;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Notification;
use Proxynth\Larawebhook\Commands\SkeletonCommand;
use Proxynth\Larawebhook\Middleware\ValidateWebhook;
use Proxynth\Larawebhook\Notifications\Channels\SlackWebhookChannel;
use Proxynth\Larawebhook\Services\FailureDetector;
use Proxynth\Larawebhook\Services\NotificationSender;
use Proxynth\Larawebhook\Services\WebhookLogger;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LarawebhookServiceProvider extends PackageServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->publishes([
            __DIR__.'/../config/larawebhook.php' => config_path('larawebhook.php'),
        ]);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ]);

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'larawebhook');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/larawebhook'),
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
            ->hasConfigFile()
            ->hasMigrations([
                'create_webhook_logs_table',
            ])->hasCommands(SkeletonCommand::class);
    }

    /**
     * Register notification-related services.
     */
    private function registerServices(): void
    {
        // Register main Larawebhook class as singleton
        $this->app->singleton(Larawebhook::class, function () {
            return new Larawebhook;
        });

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
                $app->make(NotificationSender::class)
            );
        });
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
}
