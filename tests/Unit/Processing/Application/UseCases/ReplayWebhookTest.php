<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\Data\WebhookLogData;
use Proxynth\Larawebhook\Audit\Application\ReadModels\WebhookLogSummary;
use Proxynth\Larawebhook\Audit\Domain\Events\WebhookLogged;
use Proxynth\Larawebhook\Audit\Domain\Exceptions\PayloadNotAvailable;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Audit\Infrastructure\Logging\WebhookLogDataFactory;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Ports\SignatureValidator;
use Proxynth\Larawebhook\Ingestion\Application\Ports\WebhookSecretResolver;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Processing\Application\Commands\ReplayWebhookCommand;
use Proxynth\Larawebhook\Processing\Application\Data\ReplayableWebhook;
use Proxynth\Larawebhook\Processing\Application\Exceptions\ReplayWebhookNotAllowed;
use Proxynth\Larawebhook\Processing\Application\Ports\AuditLogRecorder;
use Proxynth\Larawebhook\Processing\Application\Ports\ReplayableWebhookRepository;
use Proxynth\Larawebhook\Processing\Application\Results\ReplayWebhookResult;
use Proxynth\Larawebhook\Processing\Application\UseCases\ReplayWebhook;
use Proxynth\Larawebhook\Processing\Domain\Events\WebhookReplayed;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;
use Proxynth\Larawebhook\Shared\Infrastructure\Laravel\Providers\LarawebhookServiceProvider;
use Proxynth\Larawebhook\Tests\Fakes\Ingestion\FakeWebhookSecretResolver;

beforeEach(function () {
    app()->register(LarawebhookServiceProvider::class, true);
    config()->set('larawebhook.services.github.webhook_secret', 'github_secret');
});

function replayableWebhook(
    int|string $id = 123,
    string $status = 'failed',
    ?array $payload = ['action' => 'opened'],
): ReplayableWebhook {
    return new ReplayableWebhook(
        id: $id,
        service: WebhookService::Github->value,
        event: 'pull_request.opened',
        payload: $payload,
        attempt: 1,
        externalId: 'delivery_123',
        idempotencyKey: 'dedupe_123',
        status: $status,
    );
}

it('replays a webhook without needing database access', function () {
    $repository = new class implements ReplayableWebhookRepository
    {
        public int|string|null $id = null;

        public function findReplayableById(int|string $id): ReplayableWebhook
        {
            $this->id = $id;

            return replayableWebhook($id);
        }
    };

    $signatureValidator = new class implements SignatureValidator
    {
        public ?ValidateWebhookCommand $command = null;

        public function validate(
            WebhookServiceIdentifier $service,
            RawPayload $payload,
            Signature $signature,
            string $secret,
        ): bool {
            $this->command = new ValidateWebhookCommand(
                service: $service,
                payload: $payload,
                signature: $signature,
                event: 'pull_request.opened',
                externalId: 'delivery_123',
                secret: $secret,
            );

            return true;
        }
    };

    $auditWriter = new class implements AuditLogRecorder
    {
        public ?RecordWebhookLogCommand $command = null;

        public function record(RecordWebhookLogCommand $command): WebhookLogData
        {
            $this->command = $command;

            $log = new WebhookLog([
                'service' => $command->service,
                'event' => $command->event,
                'status' => $command->valid ? 'success' : 'failed',
                'payload' => $command->payload,
                'attempt' => $command->attempt,
                'external_id' => $command->externalId,
                'idempotency_key' => $command->idempotencyKey,
                'error_message' => $command->errorMessage,
            ]);

            $log->id = 999;
            $log->created_at = now();
            $log->updated_at = now();

            return WebhookLogDataFactory::fromModel($log);
        }
    };

    app()->instance(ReplayableWebhookRepository::class, $repository);
    app()->instance(SignatureValidator::class, $signatureValidator);
    app()->instance(AuditLogRecorder::class, $auditWriter);

    $result = app(ReplayWebhook::class)->handle(new ReplayWebhookCommand(
        webhookLogId: 123,
        signature: incomingSignature('signature'),
    ));

    expect($repository->id)->toBe(123)
        ->and($signatureValidator->command)->toBeInstanceOf(ValidateWebhookCommand::class)
        ->and($signatureValidator->command?->payload->decoded())->toBe(['action' => 'opened'])
        ->and($auditWriter->command)->toBeInstanceOf(RecordWebhookLogCommand::class)
        ->and($auditWriter->command?->attempt)->toBe(2)
        ->and($auditWriter->command?->externalId)->toBeNull()
        ->and($auditWriter->command?->idempotencyKey)->toBeNull()
        ->and($result)->toBeInstanceOf(ReplayWebhookResult::class)
        ->and($result->log)->toBeInstanceOf(WebhookLogSummary::class)
        ->and($result->log->id)->toBe(999)
        ->and($result->log->status)->toBe('success')
        ->and($result->errorMessage)->toBeNull()
        ->and($result->events)->toHaveCount(2)
        ->and($result->events[0])->toBeInstanceOf(WebhookReplayed::class)
        ->and($result->events[1])->toBeInstanceOf(WebhookLogged::class);
});

it('throws when replaying a log without payload', function () {
    app()->instance(ReplayableWebhookRepository::class, new class implements ReplayableWebhookRepository
    {
        public function findReplayableById(int|string $id): ReplayableWebhook
        {
            return replayableWebhook($id, payload: null);
        }
    });

    app()->instance(SignatureValidator::class, new class implements SignatureValidator
    {
        public function validate(
            WebhookServiceIdentifier $service,
            RawPayload $payload,
            Signature $signature,
            string $secret,
        ): bool {
            throw new LogicException('Not expected.');
        }
    });

    app()->instance(AuditLogRecorder::class, new class implements AuditLogRecorder
    {
        public function record(RecordWebhookLogCommand $command): WebhookLogData
        {
            throw new LogicException('Not expected.');
        }
    });

    app(ReplayWebhook::class)->handle(new ReplayWebhookCommand(
        webhookLogId: 123,
        signature: incomingSignature('signature'),
    ));
})->throws(PayloadNotAvailable::class);

it('throws a clear exception when replay is not allowed by the domain', function () {
    app()->instance(ReplayableWebhookRepository::class, new class implements ReplayableWebhookRepository
    {
        public function findReplayableById(int|string $id): ReplayableWebhook
        {
            return replayableWebhook($id, status: 'received');
        }
    });

    app()->instance(SignatureValidator::class, new class implements SignatureValidator
    {
        public function validate(
            WebhookServiceIdentifier $service,
            RawPayload $payload,
            Signature $signature,
            string $secret,
        ): bool {
            throw new LogicException('Not expected.');
        }
    });

    app()->instance(AuditLogRecorder::class, new class implements AuditLogRecorder
    {
        public function record(RecordWebhookLogCommand $command): WebhookLogData
        {
            throw new LogicException('Not expected.');
        }
    });

    app(ReplayWebhook::class)->handle(new ReplayWebhookCommand(
        webhookLogId: 123,
        signature: incomingSignature('signature'),
    ));
})->throws(ReplayWebhookNotAllowed::class, 'Webhook log [123] cannot be replayed from status [received].');

it('throws when the service is unsupported', function () {
    app()->instance(ReplayableWebhookRepository::class, new class implements ReplayableWebhookRepository
    {
        public function findReplayableById(int|string $id): ReplayableWebhook
        {
            return new ReplayableWebhook(
                id: $id,
                service: 'unsupported',
                event: 'pull_request.opened',
                payload: ['action' => 'opened'],
                attempt: 1,
                externalId: 'delivery_123',
                idempotencyKey: 'dedupe_123',
                status: 'failed',
            );
        }
    });

    app()->instance(SignatureValidator::class, new class implements SignatureValidator
    {
        public function validate(
            WebhookServiceIdentifier $service,
            RawPayload $payload,
            Signature $signature,
            string $secret,
        ): bool {
            throw new LogicException('Not expected.');
        }
    });

    app()->instance(AuditLogRecorder::class, new class implements AuditLogRecorder
    {
        public function record(RecordWebhookLogCommand $command): WebhookLogData
        {
            throw new LogicException('Not expected.');
        }
    });

    app(ReplayWebhook::class)->handle(new ReplayWebhookCommand(
        webhookLogId: 123,
        signature: incomingSignature('signature'),
    ));
})->throws(WebhookException::class, "Webhook service 'unsupported' is not supported.");

it('throws when replay secret is not configured', function () {
    app()->instance(ReplayableWebhookRepository::class, new class implements ReplayableWebhookRepository
    {
        public function findReplayableById(int|string $id): ReplayableWebhook
        {
            return replayableWebhook($id);
        }
    });

    app()->instance(SignatureValidator::class, new class implements SignatureValidator
    {
        public function validate(
            WebhookServiceIdentifier $service,
            RawPayload $payload,
            Signature $signature,
            string $secret,
        ): bool {
            throw new LogicException('Not expected.');
        }
    });

    app()->instance(AuditLogRecorder::class, new class implements AuditLogRecorder
    {
        public function record(RecordWebhookLogCommand $command): WebhookLogData
        {
            throw new LogicException('Not expected.');
        }
    });

    app()->instance(WebhookSecretResolver::class, new FakeWebhookSecretResolver([
        'github' => null,
    ]));

    expect(fn () => app(ReplayWebhook::class)->handle(new ReplayWebhookCommand(
        webhookLogId: 1,
        signature: Signature::fromString('sha256=invalid'),
    )))->toThrow(WebhookException::class, 'No secret configured for service: github');
});

it('throws when webhook event state does not allow replay', function () {
    app()->instance(ReplayableWebhookRepository::class, new class implements ReplayableWebhookRepository
    {
        public function findReplayableById(int|string $id): ReplayableWebhook
        {
            return replayableWebhook(
                id: $id,
                status: 'processing',
            );
        }
    });

    expect(fn () => app(ReplayWebhook::class)->handle(new ReplayWebhookCommand(
        webhookLogId: 1,
        signature: Signature::fromString('sha256=valid'),
    )))->toThrow(ReplayWebhookNotAllowed::class);
});
