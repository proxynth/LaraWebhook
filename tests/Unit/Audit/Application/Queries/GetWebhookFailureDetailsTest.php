<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogReadRepository;
use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookFailureDetailsQuery;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;

it('delegates failure detail lookup to the read repository', function () {
    $repository = new class implements WebhookLogReadRepository
    {
        public int|string|null $id = null;

        public function paginateSummaries(ListWebhookLogsQuery $query): LengthAwarePaginator
        {
            throw new LogicException('Not expected.');
        }

        public function findDetails(int|string $id): WebhookLogDetails
        {
            throw new LogicException('Not expected.');
        }

        public function findFailureDetails(int|string $id): WebhookFailureDetails
        {
            $this->id = $id;

            return new WebhookFailureDetails(
                id: $id,
                service: 'github',
                event: 'push',
                status: 'failed',
                errorMessage: 'Invalid GitHub webhook signature.',
                attempt: 1,
                externalId: 'delivery_123',
                idempotencyKey: 'delivery_123',
                createdAt: '2026-06-16T12:00:00+00:00',
            );
        }
    };

    app()->instance(WebhookLogReadRepository::class, $repository);

    $result = app(GetWebhookFailureDetails::class)->handle(
        new GetWebhookFailureDetailsQuery(456)
    );

    expect($repository->id)->toBe(456)
        ->and($result)->toBeInstanceOf(WebhookFailureDetails::class)
        ->and($result->id)->toBe(456)
        ->and($result->service)->toBe('github')
        ->and($result->status)->toBe('failed');
});
