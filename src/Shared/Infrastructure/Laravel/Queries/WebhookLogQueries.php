<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Queries;

use Illuminate\Database\Eloquent\Collection;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final class WebhookLogQueries
{
    /** @return Collection<int, WebhookLog> */
    public function all(): Collection
    {
        return WebhookLog::latest()->get();
    }

    /** @return Collection<int, WebhookLog> */
    public function forService(string $service): Collection
    {
        return WebhookLog::service($service)->latest()->get();
    }

    /** @return Collection<int, WebhookLog> */
    public function failed(): Collection
    {
        return WebhookLog::failed()->latest()->get();
    }

    /** @return Collection<int, WebhookLog> */
    public function successful(): Collection
    {
        return WebhookLog::successful()->latest()->get();
    }
}
