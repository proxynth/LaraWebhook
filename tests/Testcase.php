<?php

namespace Proxynth\Larawebhook\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Providers\LarawebhookServiceProvider;

class Testcase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Proxynth\\Larawebhook\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LarawebhookServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Run migrations
        $createWebhookLogsTable = include __DIR__.'/../database/migrations/001_create_webhook_logs_table.php';
        $createWebhookLogsTable->up();

        $addIdempotencyKey = include __DIR__.'/../database/migrations/002_add_idempotency_key_to_webhook_logs_table.php';
        $addIdempotencyKey->up();

        $createProcessedWebhookEventsTable = include __DIR__.'/../database/migrations/003_create_processed_webhook_events_table.php';
        $createProcessedWebhookEventsTable->up();

        $createWebhookLogsTable = include __DIR__.'/../database/migrations/004_remove_webhook_logs_idempotency_unique_index.php';
        $createWebhookLogsTable->up();
    }
}
