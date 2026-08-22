<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence;

use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogPage;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogReadRepository;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;

final class EloquentWebhookLogReadRepository implements WebhookLogReadRepository
{
    public function paginateSummaries(ListWebhookLogsQuery $query): WebhookLogPage
    {
        $logs = WebhookLog::query()
            ->when($query->service !== null, fn ($builder) => $builder->where('service', $query->service))
            ->when($query->status !== null, fn ($builder) => $builder->where('status', $query->status))
            ->when($query->event !== null, fn ($builder) => $builder->where('event', $query->event))
            ->when($query->date !== null, fn ($builder) => $builder->whereDate('created_at', $query->date))
            ->latest()
            ->paginate($query->perPage);

        $factory = new WebhookLogReadModelFactory;

        $mapped = $logs->through(fn (WebhookLog $log): WebhookLogSummary => $factory->summary($log));

        return new WebhookLogPage(
            items: array_values($mapped->items()),
            total: $mapped->total(),
            perPage: $mapped->perPage(),
            currentPage: $mapped->currentPage(),
            lastPage: $mapped->lastPage(),
            links: [
                'first' => $mapped->url(1),
                'last' => $mapped->url($mapped->lastPage()),
                'prev' => $mapped->previousPageUrl(),
                'next' => $mapped->nextPageUrl(),
            ],
        );
    }

    public function findDetails(int|string $id): WebhookLogDetails
    {
        $log = WebhookLog::query()->findOrFail($id);

        return (new WebhookLogReadModelFactory)->details($log);
    }

    public function findFailureDetails(int|string $id): WebhookFailureDetails
    {
        $log = WebhookLog::query()
            ->whereKey($id)
            ->where('status', 'failed')
            ->firstOrFail();

        return (new WebhookLogReadModelFactory)->failureDetails($log);
    }
}
