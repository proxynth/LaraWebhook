<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Services;

use Illuminate\Support\Facades\Cache;
use Proxynth\Larawebhook\Models\WebhookLog;

/**
 * Detects repeated webhook failures for a service/event combination.
 *
 * This service is responsible for:
 * - Counting consecutive failures within a time window
 * - Determining if the failure threshold has been reached
 * - Managing notification cooldowns to prevent spam
 */
class FailureDetector
{
    private const CACHE_PREFIX = 'larawebhook_notification_';

    /**
     * Check if a notification should be sent for repeated failures.
     *
     * @return array{should_notify: bool, failure_count: int, latest_log: ?WebhookLog}
     */
    public function checkForRepeatedFailures(string $service, string $event): array
    {
        $threshold = $this->getThreshold();
        $windowMinutes = $this->getWindowMinutes();

        $failureCount = $this->countRecentFailures($service, $event, $windowMinutes);
        $latestLog = $this->getLatestFailedLog($service, $event);

        $shouldNotify = $failureCount >= $threshold
            && $latestLog !== null
            && $this->canSendNotification($service, $event);

        return [
            'should_notify' => $shouldNotify,
            'failure_count' => $failureCount,
            'latest_log' => $latestLog,
        ];
    }

    /**
     * Count recent failures for a service/event combination.
     */
    public function countRecentFailures(string $service, string $event, ?int $windowMinutes = null): int
    {
        $windowMinutes ??= $this->getWindowMinutes();

        return WebhookLog::query()
            ->service($service)
            ->event($event)
            ->failed()
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();
    }

    /**
     * Get the latest failed log for a service/event.
     */
    public function getLatestFailedLog(string $service, string $event): ?WebhookLog
    {
        return WebhookLog::query()
            ->service($service)
            ->event($event)
            ->failed()
            ->latest()
            ->first();
    }

    /**
     * Check if a notification can be sent (respects cooldown).
     */
    public function canSendNotification(string $service, string $event): bool
    {
        $cacheKey = $this->getCacheKey($service, $event);
        $lastNotification = Cache::get($cacheKey);

        if ($lastNotification === null) {
            return true;
        }

        $cooldownMinutes = $this->getCooldownMinutes();

        return now()->diffInMinutes($lastNotification) >= $cooldownMinutes;
    }

    /**
     * Mark that a notification was sent for a service/event.
     */
    public function markNotificationSent(string $service, string $event): void
    {
        $cacheKey = $this->getCacheKey($service, $event);
        $cooldownMinutes = $this->getCooldownMinutes();

        Cache::put($cacheKey, now(), $cooldownMinutes * 60);
    }

    /**
     * Clear the notification cooldown for a service/event.
     */
    public function clearCooldown(string $service, string $event): void
    {
        $cacheKey = $this->getCacheKey($service, $event);
        Cache::forget($cacheKey);
    }

    /**
     * Get the cache key for tracking notification cooldowns.
     */
    private function getCacheKey(string $service, string $event): string
    {
        return self::CACHE_PREFIX.md5("{$service}_{$event}");
    }

    /**
     * Get the failure threshold from config.
     */
    private function getThreshold(): int
    {
        return (int) config('larawebhook.notifications.failure_threshold', 3);
    }

    /**
     * Get the time window in minutes from config.
     */
    private function getWindowMinutes(): int
    {
        return (int) config('larawebhook.notifications.failure_window_minutes', 30);
    }

    /**
     * Get the cooldown period in minutes from config.
     */
    private function getCooldownMinutes(): int
    {
        return (int) config('larawebhook.notifications.cooldown_minutes', 30);
    }
}
