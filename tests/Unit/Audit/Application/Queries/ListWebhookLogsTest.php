<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogReadRepository;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogs;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;

it('delegates pagination to the read repository', function () {
    $repository = new class implements WebhookLogReadRepository
    {
        public ?ListWebhookLogsQuery $query = null;

        public function paginateSummaries(ListWebhookLogsQuery $query): LengthAwarePaginator
        {
            $this->query = $query;

            return new LengthAwarePaginator([
                new WebhookLogSummary(
                    id: 2,
                    service: 'github',
                    event: 'push',
                    status: 'success',
                    attempt: 0,
                    externalId: 'delivery_2',
                    idempotencyKey: 'delivery_2',
                    createdAt: '2026-06-16T12:01:00+00:00',
                ),
                new WebhookLogSummary(
                    id: 1,
                    service: 'stripe',
                    event: 'invoice.paid',
                    status: 'failed',
                    attempt: 1,
                    externalId: 'delivery_1',
                    idempotencyKey: 'delivery_1',
                    createdAt: '2026-06-16T12:00:00+00:00',
                ),
            ], 2, $query->perPage);
        }

        public function findDetails(int|string $id): WebhookLogDetails
        {
            throw new LogicException('Not expected.');
        }

        public function findFailureDetails(int|string $id): WebhookFailureDetails
        {
            throw new LogicException('Not expected.');
        }
    };

    app()->instance(WebhookLogReadRepository::class, $repository);

    $result = app(ListWebhookLogs::class)->handle(new ListWebhookLogsQuery(
        service: 'stripe',
        status: 'failed',
        event: 'invoice.paid',
        date: '2026-06-16',
        perPage: 25,
    ));

    expect($repository->query)->toBeInstanceOf(ListWebhookLogsQuery::class)
        ->and($repository->query?->service)->toBe('stripe')
        ->and($repository->query?->status)->toBe('failed')
        ->and($repository->query?->event)->toBe('invoice.paid')
        ->and($repository->query?->date)->toBe('2026-06-16')
        ->and($result->total())->toBe(2)
        ->and($result->items()[0])->toBeInstanceOf(WebhookLogSummary::class)
        ->and($result->items()[0]->id)->toBe(2);
});
