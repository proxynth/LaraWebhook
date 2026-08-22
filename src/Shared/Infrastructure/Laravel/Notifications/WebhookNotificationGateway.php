<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Notifications;

use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\FailureDetector;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Notifications\NotificationSender;

final readonly class WebhookNotificationGateway
{
    public function __construct(
        private FailureDetector $failureDetector,
        private NotificationSender $notificationSender,
    ) {}

    public function failureCount(string $service, string $event): int
    {
        return $this->failureDetector->countRecentFailures($service, $event);
    }

    public function canSend(string $service, string $event): bool
    {
        return $this->failureDetector->canSendNotification($service, $event);
    }

    public function sendIfNeeded(string $service, string $event): bool
    {
        return $this->notificationSender->sendIfNeeded($service, $event);
    }

    public function enabled(): bool
    {
        return $this->notificationSender->isEnabled();
    }

    /** @return array<string> */
    public function channels(): array
    {
        return $this->notificationSender->getChannels();
    }

    public function clearCooldown(string $service, string $event): void
    {
        $this->failureDetector->clearCooldown($service, $event);
    }
}
