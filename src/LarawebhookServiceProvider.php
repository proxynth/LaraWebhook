<?php

namespace Proxynth\Larawebhook;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Proxynth\Larawebhook\Commands\SkeletonCommand;
use Proxynth\Larawebhook\Middleware\ValidateWebhook;
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

        // Register middleware alias
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('validate-webhook', ValidateWebhook::class);

        AboutCommand::add('Larawebhook', fn () => [
            'name' => 'Larawebhook',
            'Version' => '0.0.0',
        ]);
    }

    public function configurePackage(Package $package): void
    {
        $package->name('larawebhook')
            ->hasConfigFile()
            ->hasMigrations([
                'create_webhook_logs_table',
            ])->hasCommands(SkeletonCommand::class);
    }
}
