<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Proxynth\Larawebhook\Models\WebhookLog;

/**
 * Event dispatched when a webhook failure notification is sent.
 *
 * This event can be used to:
 * - Log notification activity
 * - Trigger additional actions after notifications
 * - Integrate with external monitoring systems
 */
class WebhookNotificationSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WebhookLog $log,
        public readonly int $failureCount
    ) {}
}
