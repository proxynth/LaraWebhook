<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Laravel\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Ingestion\Application\Commands\ReceiveWebhookCommand;
use Proxynth\Larawebhook\Ingestion\Application\Data\IncomingWebhookSignature;
use Proxynth\Larawebhook\Ingestion\Application\UseCases\ReceiveWebhook;
use Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Jobs\RetryWebhookJob;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhook
{
    public function __construct(
        private readonly ReceiveWebhook $receiveWebhook,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws Exception
     */
    public function handle(Request $request, Closure $next, string $service): Response
    {
        $webhookService = WebhookService::tryFromString($service);

        if ($webhookService === null) {
            return response()
                ->json("Service '{$service}' is not supported.", Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->getContent();

        if ($payload === '') {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => 'Payload is empty.',
                ], Response::HTTP_BAD_REQUEST);
        }

        $signatureHeader = $webhookService->signatureHeader();
        $signatureValue = $this->headerValue($request, $signatureHeader);

        if ($signatureValue === null) {
            return response()->json([
                'status' => 'error',
                'message' => "Missing {$signatureHeader} header.",
            ], Response::HTTP_BAD_REQUEST);
        }

        $timestampHeader = $webhookService->timestampHeader();
        $timestampValue = null;

        if ($timestampHeader !== null) {
            $timestampValue = $this->headerValue($request, $timestampHeader);

            if ($timestampValue === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Missing {$timestampHeader} header.",
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $signature = new IncomingWebhookSignature(
            value: $signatureValue,
            timestamp: $timestampValue,
        );

        $result = $this->receiveWebhook->handle(new ReceiveWebhookCommand(
            service: $webhookService,
            payload: $payload,
            signature: $signature,
            externalIdHeaderValue: $this->externalIdHeaderValue($request, $webhookService),
        ));

        if ($result->isAlreadyProcessed()) {
            return response()
                ->json([
                    'status' => 'already_processed',
                    'external_id' => $result->externalId,
                ], Response::HTTP_OK);
        }

        if ($result->isSecretNotConfigured()) {
            return response()->json([
                'status' => 'secret_not_configured',
                'message' => $result->errorMessage ?? "Webhook secret not configured for $service.",
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($result->isAcceptedForRetry()) {
            $this->dispatchRetryJob(
                payload: $payload,
                signature: $signature,
                service: $webhookService->value,
                event: $result->event ?? 'unknown',
                secret: $result->secret ?? '',
                externalId: $result->idempotencyKey,
            );

            return response()
                ->json([
                    'status' => 'accepted_for_retry',
                    'message' => $result->errorMessage ?? 'Webhook validation failed, queued for retry',
                    'external_id' => $result->idempotencyKey,
                ], Response::HTTP_ACCEPTED);
        }

        if ($result->isFailed()) {
            return response()->json([
                'status' => 'failed',
                'message' => $result->errorMessage ?? 'Webhook validation failed.',
            ], $result->failureStatusCode ?? Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function externalIdHeaderValue(Request $request, WebhookService $webhookService): ?string
    {
        $externalIdHeader = $webhookService->externalIdHeader();

        if ($externalIdHeader === null) {
            return null;
        }

        $value = $request->header($externalIdHeader);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function dispatchRetryJob(
        string $payload,
        IncomingWebhookSignature $signature,
        string $service,
        string $event,
        string $secret,
        ?string $externalId
    ): void {
        $delays = config('larawebhook.retries.delays', [1, 5, 10]);

        $firstDelay = is_array($delays) && isset($delays[0])
            ? (int) $delays[0]
            : 1;

        RetryWebhookJob::dispatch(
            $payload,
            $signature,
            $service,
            $event,
            $secret,
            1,
            $externalId
        )->delay(now()->addSeconds($firstDelay));
    }

    private function headerValue(Request $request, string $header): ?string
    {
        $value = $request->header($header);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
