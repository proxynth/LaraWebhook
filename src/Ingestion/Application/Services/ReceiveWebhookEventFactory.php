<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Services;

use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;
use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookReceived;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookRejected;
use Proxynth\Larawebhook\Ingestion\Domain\Events\WebhookValidated;

final class ReceiveWebhookEventFactory
{
    public function received(string $provider, string $event, ?string $externalId): WebhookReceived
    {
        return new WebhookReceived($provider, $event, $externalId);
    }

    public function validated(string $provider, string $event, ?string $externalId): WebhookValidated
    {
        return new WebhookValidated($provider, $event, $externalId);
    }

    public function rejected(string $provider, string $event, ?string $externalId, string $reason): WebhookRejected
    {
        return new WebhookRejected($provider, $event, $externalId, $reason);
    }

    public function logged(WebhookLogData $log): WebhookLogged
    {
        return new WebhookLogged($log->id, $log->service, $log->event, $log->status);
    }
}
