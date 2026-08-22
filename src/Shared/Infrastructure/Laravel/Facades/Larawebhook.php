<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

/**
 * @method static bool validate(string $payload, Signature $signature, string|WebhookServiceIdentifier $service)
 * @method static WebhookLog validateAndLog(string $payload, Signature $signature, string|WebhookServiceIdentifier $service, string $event)
 * @method static WebhookLog validateWithRetries(string $payload, Signature $signature, string|WebhookServiceIdentifier $service, string $event)
 * @method static WebhookLog logSuccess(string $service, string $event, array $payload, int $attempt = 0, ?string $idempotencyKey = null)
 * @method static WebhookLog logFailure(string $service, string $event, array $payload, string $errorMessage, int $attempt = 0, ?string $idempotencyKey = null)
 * @method static Collection logs()
 * @method static Collection logsForService(string|WebhookServiceIdentifier $service)
 * @method static Collection failedLogs()
 * @method static Collection successfulLogs()
 * @method static int getFailureCount(string $service, string $event)
 * @method static bool canSendNotification(string $service, string $event)
 * @method static bool sendNotificationIfNeeded(string $service, string $event)
 * @method static bool notificationsEnabled()
 * @method static array<int, string> getNotificationChannels()
 * @method static void clearCooldown(string $service, string $event)
 * @method static ?string getSecret(string|WebhookServiceIdentifier $service)
 * @method static bool isServiceSupported(string $service)
 * @method static array<int, string> supportedServices()
 * @method static array<int, string> services()
 * @method static ?WebhookServiceIdentifier service(string $service)
 *
 * @see \Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Larawebhook
 */
class Larawebhook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Larawebhook::class;
    }
}
