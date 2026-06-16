<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\UseCases;

use Exception;
use Proxynth\Larawebhook\Audit\Application\Commands\RecordWebhookLogCommand;
use Proxynth\Larawebhook\Audit\Application\UseCases\RecordWebhookLog;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ReceiveWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ValidateWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Results\ReceiveWebhookResult;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Provider;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Processing\Application\Ports\IdempotencyResolver;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\DeliveryAttempt;
use Proxynth\Larawebhook\Processing\Domain\ValueObjects\IdempotencyKey;
use Symfony\Component\HttpFoundation\Response;

final readonly class ReceiveWebhook
{
    public function __construct(
        private IdempotencyResolver $idempotencyResolver,
        private ValidateWebhook $validateWebhook,
        private RecordWebhookLog $recordWebhookLog,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(ReceiveWebhookCommand $command): ReceiveWebhookResult
    {
        $provider = Provider::fromString($command->service->value);
        $rawPayload = RawPayload::fromString($command->payload);
        $decodedPayload = $rawPayload->decoded();

        $event = $this->extractEventType($decodedPayload, $command->service);

        $externalId = $this->extractExternalId(
            decodedPayload: $decodedPayload,
            service: $command->service,
            externalIdHeaderValue: $command->externalIdHeaderValue,
        );

        $idempotencyKeyValue = $this->idempotencyResolver->resolve(
            service: $provider->value(),
            payload: $decodedPayload,
            externalId: $externalId,
            event: $event,
        );

        $idempotencyKey = IdempotencyKey::optional($idempotencyKeyValue);

        if ($idempotencyKey !== null && WebhookLog::existsForExternalId($provider->value(), $idempotencyKey->value())) {
            return ReceiveWebhookResult::alreadyProcessed(
                externalId: $externalId,
                idempotencyKey: $idempotencyKey->value(),
            );
        }

        $secret = $command->service->secret();

        if (empty($secret)) {
            return ReceiveWebhookResult::secretNotConfigured($provider->value());
        }

        $validation = $this->validateWebhook->handle(new ValidateWebhookCommand(
            service: $command->service,
            payload: $rawPayload,
            signature: $command->signature,
            event: $event,
            externalId: $externalId,
            secret: $secret,
        ));

        $log = $this->recordWebhookLog->handle(new RecordWebhookLogCommand(
            service: $validation->service,
            event: $validation->event,
            valid: $validation->isValid(),
            payload: $validation->payload,
            attempt: DeliveryAttempt::initial()->value(),
            externalId: $idempotencyKey?->value(),
            errorMessage: $validation->errorMessage,
        ));

        if ($validation->isValid()) {
            return ReceiveWebhookResult::success($log);
        }

        if ($this->shouldRetryAsync()) {
            return ReceiveWebhookResult::acceptedForRetry(
                log: $log,
                event: $event,
                secret: $secret,
                idempotencyKey: $idempotencyKey?->value(),
            );
        }

        return ReceiveWebhookResult::failed(
            log: $log,
            statusCode: $this->failureStatusCode($log),
        );
    }

    private function extractEventType(mixed $decodedPayload, WebhookService $service): string
    {
        if (! is_array($decodedPayload)) {
            return 'unknown';
        }

        return $service->parser()->extractEventType($decodedPayload);
    }

    private function extractExternalId(
        mixed $decodedPayload,
        WebhookService $service,
        ?string $externalIdHeaderValue
    ): ?string {
        if (! is_array($decodedPayload)) {
            return $externalIdHeaderValue;
        }

        return $service->parser()->extractExternalId($decodedPayload, $externalIdHeaderValue);
    }

    private function shouldRetryAsync(): bool
    {
        return config('larawebhook.retries.enabled', true)
            && config('larawebhook.retries.async', false);
    }

    private function failureStatusCode(WebhookLog $log): int
    {
        $errorMessage = $log->error_message ?? '';

        return str_contains($errorMessage, 'format') || str_contains($errorMessage, 'expired')
            ? Response::HTTP_BAD_REQUEST
            : Response::HTTP_FORBIDDEN;
    }
}
