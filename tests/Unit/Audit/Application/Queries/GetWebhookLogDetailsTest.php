<?php

use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogPage;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookLogReadRepository;
use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookLogDetails;
use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookLogDetailsQuery;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogsQuery;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookFailureDetails;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogDetails;

it('delegates detail lookup to the read repository', function () {
    $repository = new class implements WebhookLogReadRepository
    {
        public int|string|null $id = null;

        public function paginateSummaries(ListWebhookLogsQuery $query): WebhookLogPage
        {
            throw new LogicException('Not expected.');
        }

        public function findDetails(int|string $id): WebhookLogDetails
        {
            $this->id = $id;

            return new WebhookLogDetails(
                id: $id,
                service: 'stripe',
                event: 'invoice.paid',
                status: 'failed',
                payload: ['invoice' => 'in_123'],
                errorMessage: 'Invalid signature.',
                attempt: 1,
                externalId: 'delivery_123',
                idempotencyKey: 'delivery_123',
                createdAt: '2026-06-16T12:00:00+00:00',
                updatedAt: '2026-06-16T12:01:00+00:00',
            );
        }

        public function findFailureDetails(int|string $id): WebhookFailureDetails
        {
            throw new LogicException('Not expected.');
        }
    };

    app()->instance(WebhookLogReadRepository::class, $repository);

    $result = app(GetWebhookLogDetails::class)->handle(
        new GetWebhookLogDetailsQuery(123)
    );

    expect($repository->id)->toBe(123)
        ->and($result)->toBeInstanceOf(WebhookLogDetails::class)
        ->and($result->id)->toBe(123)
        ->and($result->service)->toBe('stripe')
        ->and($result->event)->toBe('invoice.paid');
});
