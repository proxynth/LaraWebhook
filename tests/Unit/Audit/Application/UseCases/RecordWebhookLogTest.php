<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;
use Proxynth\Larawebhook\Audit\Application\Ports\WebhookAuditLogWriter;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Audit\Infrastructure\Logging\WebhookLogDataFactory;

beforeEach(function () {
    app()->forgetInstance(WebhookAuditLogWriter::class);
});

it('delegates successful records to the audit writer', function () {
    $fakeWriter = new class implements WebhookAuditLogWriter
    {
        /** @var list<RecordWebhookLogCommand> */
        public array $commands = [];

        public function record(RecordWebhookLogCommand $command): WebhookLogData
        {
            $this->commands[] = $command;

            return WebhookLogDataFactory::fromModel(WebhookLog::make([
                'service' => $command->service,
                'event' => $command->event,
                'status' => $command->valid ? 'success' : 'failed',
                'payload' => $command->payload,
                'attempt' => $command->attempt,
                'external_id' => $command->externalId,
                'idempotency_key' => $command->idempotencyKey,
                'error_message' => $command->errorMessage,
            ]));
        }
    };

    app()->instance(WebhookAuditLogWriter::class, $fakeWriter);

    $log = app(RecordWebhookLog::class)->handle(new RecordWebhookLogCommand(
        service: 'github',
        event: 'push',
        valid: true,
        payload: ['ref' => 'refs/heads/main'],
        attempt: 0,
        externalId: 'delivery_123',
        idempotencyKey: 'delivery_123',
    ));

    expect($log)->toBeInstanceOf(WebhookLogData::class)
        ->and($log->status)->toBe('success')
        ->and($fakeWriter->commands)->toHaveCount(1)
        ->and($fakeWriter->commands[0]->service)->toBe('github')
        ->and($fakeWriter->commands[0]->valid)->toBeTrue();
});

it('delegates failed records to the audit writer', function () {
    $fakeWriter = new class implements WebhookAuditLogWriter
    {
        /** @var list<RecordWebhookLogCommand> */
        public array $commands = [];

        public function record(RecordWebhookLogCommand $command): WebhookLogData
        {
            $this->commands[] = $command;

            return WebhookLogDataFactory::fromModel(WebhookLog::make([
                'service' => $command->service,
                'event' => $command->event,
                'status' => $command->valid ? 'success' : 'failed',
                'payload' => $command->payload,
                'attempt' => $command->attempt,
                'external_id' => $command->externalId,
                'idempotency_key' => $command->idempotencyKey,
                'error_message' => $command->errorMessage,
            ]));
        }
    };

    app()->instance(WebhookAuditLogWriter::class, $fakeWriter);

    $log = app(RecordWebhookLog::class)->handle(new RecordWebhookLogCommand(
        service: 'github',
        event: 'push',
        valid: false,
        payload: ['ref' => 'refs/heads/main'],
        attempt: 1,
        externalId: 'delivery_123',
        idempotencyKey: 'delivery_123',
        errorMessage: 'Invalid GitHub webhook signature.',
    ));

    expect($log)->toBeInstanceOf(WebhookLogData::class)
        ->and($log->status)->toBe('failed')
        ->and($fakeWriter->commands)->toHaveCount(1)
        ->and($fakeWriter->commands[0]->valid)->toBeFalse()
        ->and($fakeWriter->commands[0]->errorMessage)->toBe('Invalid GitHub webhook signature.');
});

it('records multiple audit logs with the same idempotency key', function () {
    $useCase = app(RecordWebhookLog::class);

    $useCase->handle(new RecordWebhookLogCommand(
        service: 'github',
        event: 'opened',
        valid: true,
        payload: ['action' => 'opened'],
        attempt: 0,
        externalId: 'delivery_123',
        idempotencyKey: 'delivery_123',
        errorMessage: null,
    ));

    $useCase->handle(new RecordWebhookLogCommand(
        service: 'github',
        event: 'opened',
        valid: false,
        payload: ['action' => 'opened'],
        attempt: 1,
        externalId: 'delivery_123',
        idempotencyKey: 'delivery_123',
        errorMessage: 'Retry failed',
    ));

    expect(WebhookLog::query()->count())->toBe(2);
});
