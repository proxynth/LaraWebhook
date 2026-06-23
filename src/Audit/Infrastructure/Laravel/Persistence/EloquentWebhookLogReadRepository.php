<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence;

use Illuminate\Pagination\LengthAwarePaginator;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogReadRepository;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final class EloquentWebhookLogReadRepository implements WebhookLogReadRepository
{
    public function paginateSummaries(ListWebhookLogsQuery $query): LengthAwarePaginator
    {
        $logs = WebhookLog::query()
            ->when($query->service !== null, fn ($builder) => $builder->where('service', $query->service))
            ->when($query->status !== null, fn ($builder) => $builder->where('status', $query->status))
            ->when($query->event !== null, fn ($builder) => $builder->where('event', $query->event))
            ->when($query->date !== null, fn ($builder) => $builder->whereDate('created_at', $query->date))
            ->latest()
            ->paginate($query->perPage);

        return $logs->through(
            fn (WebhookLog $log): WebhookLogSummary => WebhookLogSummary::fromModel($log)
        );
    }

    public function findDetails(int|string $id): WebhookLogDetails
    {
        $log = WebhookLog::query()->findOrFail($id);

        return WebhookLogDetails::fromModel($log);
    }

    public function findFailureDetails(int|string $id): WebhookFailureDetails
    {
        $log = WebhookLog::query()
            ->whereKey($id)
            ->where('status', 'failed')
            ->firstOrFail();

        return WebhookFailureDetails::fromModel($log);
    }
}
