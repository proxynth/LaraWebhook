<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Application\Queries;

use Illuminate\Pagination\LengthAwarePaginator;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogReadModel;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final readonly class ListWebhookLogs
{
    public function handle(ListWebhookLogsQuery $query): LengthAwarePaginator
    {
        $logs = WebhookLog::query()
            ->when($query->service !== null, fn ($builder) => $builder->where('service', $query->service))
            ->when($query->status !== null, fn ($builder) => $builder->where('status', $query->status))
            ->when($query->event !== null, fn ($builder) => $builder->where('event', $query->event))
            ->when($query->date !== null, fn ($builder) => $builder->whereDate('created_at', $query->date))
            ->latest()
            ->paginate($query->perPage);

        return $logs->through(
            fn (WebhookLog $log): WebhookLogReadModel => WebhookLogReadModel::fromModel($log)
        );
    }
}
