<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Models\WebhookLog;

/**
 * @method static bool validate(string $payload, string $signature, string|WebhookService $service)
 * @method static WebhookLog validateAndLog(string $payload, string $signature, string|WebhookService $service, string $event)
 * @method static WebhookLog validateWithRetries(string $payload, string $signature, string|WebhookService $service, string $event)
 * @method static WebhookLog logSuccess(string $service, string $event, array $payload, int $attempt = 0)
 * @method static WebhookLog logFailure(string $service, string $event, array $payload, string $errorMessage, int $attempt = 0)
 * @method static Collection logs()
 * @method static Collection logsForService(string|WebhookService $service)
 * @method static Collection failedLogs()
 * @method static Collection successfulLogs()
 * @method static int getFailureCount(string $service, string $event)
 * @method static bool canSendNotification(string $service, string $event)
 * @method static bool sendNotificationIfNeeded(string $service, string $event)
 * @method static bool notificationsEnabled()
 * @method static array getNotificationChannels()
 * @method static void clearCooldown(string $service, string $event)
 * @method static ?string getSecret(string|WebhookService $service)
 * @method static bool isServiceSupported(string $service)
 * @method static array supportedServices()
 * @method static array services()
 * @method static ?WebhookService service(string $service)
 *
 * @see \Proxynth\Larawebhook\Larawebhook
 */
class Larawebhook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Proxynth\Larawebhook\Larawebhook::class;
    }
}
