<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\UseCases;

use Exception;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ReceiveWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Results\ReceiveWebhookResult;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Validation\WebhookValidatorFactory;
use Proxynth\Larawebhook\Processing\Application\Ports\IdempotencyResolver;
use Symfony\Component\HttpFoundation\Response;

final readonly class ReceiveWebhook
{
    private const INITIAL_ATTEMPT = 0;

    public function __construct(
        private WebhookValidatorFactory $validatorFactory,
        private IdempotencyResolver $idempotencyResolver,
    ) {}

    /**
     * @throws WebhookException
     * @throws Exception
     */
    public function handle(ReceiveWebhookCommand $command): ReceiveWebhookResult
    {
        $decodedPayload = json_decode($command->payload, true);

        $event = $this->extractEventType($decodedPayload, $command->service);

        $externalId = $this->extractExternalId(
            decodedPayload: $decodedPayload,
            service: $command->service,
            externalIdHeaderValue: $command->externalIdHeaderValue,
        );

        $serviceName = $command->service->value;

        $payloadForIdempotency = is_array($decodedPayload) ? $decodedPayload : [];

        $idempotencyKey = $this->idempotencyResolver->resolve(
            service: $serviceName,
            payload: $payloadForIdempotency,
            externalId: $externalId,
            event: $event,
        );

        if ($idempotencyKey !== null && WebhookLog::existsForExternalId($serviceName, $idempotencyKey)) {
            return ReceiveWebhookResult::alreadyProcessed(
                externalId: $externalId,
                idempotencyKey: $idempotencyKey,
            );
        }

        $secret = $command->service->secret();

        if (empty($secret)) {
            return ReceiveWebhookResult::secretNotConfigured($serviceName);
        }

        $log = $this->validatorFactory->forService($command->service)
            ->validateAndLog(
                payload: $command->payload,
                signature: $command->signature,
                service: $serviceName,
                event: $event,
                attempt: self::INITIAL_ATTEMPT,
                externalId: $idempotencyKey,
            );

        if ($log->status !== 'failed') {
            return ReceiveWebhookResult::success($log);
        }

        if ($this->shouldRetryAsync()) {
            return ReceiveWebhookResult::acceptedForRetry(
                log: $log,
                event: $event,
                secret: $secret,
                idempotencyKey: $idempotencyKey,
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
