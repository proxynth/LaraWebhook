<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Infrastructure\Laravel\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Ingestion\Infrastructure\Validation\WebhookValidatorFactory;
use Proxynth\Larawebhook\Processing\Application\Ports\IdempotencyResolver;
use Proxynth\Larawebhook\Processing\Infrastructure\Laravel\Jobs\RetryWebhookJob;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhook
{
    private WebhookValidatorFactory $webhookValidatorFactory;

    private IdempotencyResolver $idempotencyResolver;

    public function __construct(
        ?WebhookValidatorFactory $webhookValidatorFactory = null,
        ?IdempotencyResolver $idempotencyResolver = null,
    ) {
        $this->idempotencyResolver = $idempotencyResolver ?? app(IdempotencyResolver::class);
        $this->webhookValidatorFactory = $webhookValidatorFactory ?? app(WebhookValidatorFactory::class);
    }

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
            return response("Service {$service} is not supported.", Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->getContent();
        $signatureHeader = $webhookService->signatureHeader();
        $signature = $request->header($signatureHeader);

        if (empty($signature)) {
            return response("Missing {$signatureHeader} header.", Response::HTTP_BAD_REQUEST);
        }

        // Handle services with separate timestamp headers (e.g., Slack)
        $signature = $this->buildSignatureWithTimestamp($request, $webhookService, $signature);

        if (empty($payload)) {
            return response('Request body is empty.', Response::HTTP_BAD_REQUEST);
        }

        $decodedPayload = json_decode($payload, true);
        $event = $this->extractEventType($decodedPayload, $webhookService);
        $externalId = $this->extractExternalId($request, $webhookService, $decodedPayload);

        $idempotencyKey = $this->idempotencyResolver->resolve(
            service: $service,
            payload: $decodedPayload,
            externalId: $externalId,
            event: $event
        );

        // Check for duplicate webhook (idempotency)
        if ($idempotencyKey !== null && WebhookLog::existsForExternalId($service, $idempotencyKey)) {
            return response()->json([
                'status' => 'already_processed',
                'external_id' => $externalId,
            ], Response::HTTP_OK);
        }

        $secret = $webhookService->secret();
        if (empty($secret)) {
            return response("Webhook secret not configured for {$service}.", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $log = $this->webhookValidatorFactory->forService($webhookService)->validateAndLog($payload, $signature, $service, $event, 0, $idempotencyKey);

        if ($log->status === 'failed') {
            // Check if async retries are enabled
            if ($this->shouldRetryAsync()) {
                $this->dispatchRetryJob($payload, $signature, $service, $event, $secret, $idempotencyKey);

                return response()->json([
                    'status' => 'accepted_for_retry',
                    'message' => 'Webhook validation failed, queued for retry',
                    'external_id' => $idempotencyKey,
                ], Response::HTTP_ACCEPTED);
            }

            $statusCode = str_contains($log->error_message, 'format') || str_contains($log->error_message, 'expired')
                ? Response::HTTP_BAD_REQUEST
                : Response::HTTP_FORBIDDEN;

            return response($log->error_message, $statusCode);
        }

        return $next($request);
    }

    /**
     * Build the signature string including timestamp for services that use separate headers.
     *
     * Some services (like Slack) send the timestamp in a separate header.
     * We combine them into a single string for the validator.
     */
    private function buildSignatureWithTimestamp(Request $request, WebhookService $service, string $signature): string
    {
        $timestampHeader = $service->timestampHeader();

        if ($timestampHeader === null) {
            return $signature;
        }

        $timestamp = $request->header($timestampHeader);

        if (empty($timestamp)) {
            return $signature;
        }

        // Combine timestamp and signature: "timestamp:signature"
        return "{$timestamp}:{$signature}";
    }

    /**
     * Extract event type from the webhook payload using the service's parser.
     *
     * @param  array<string, mixed>|null  $data  The decoded payload data
     */
    private function extractEventType(?array $data, WebhookService $service): string
    {
        if (! is_array($data)) {
            return 'unknown';
        }

        return $service->parser()->extractEventType($data);
    }

    /**
     * Extract the external ID for idempotency from the request.
     *
     * @param  array<string, mixed>|null  $data  The decoded payload data
     */
    private function extractExternalId(Request $request, WebhookService $service, ?array $data): ?string
    {
        $externalIdHeader = $service->externalIdHeader();
        $headerValue = $externalIdHeader ? $request->header($externalIdHeader) : null;

        if (! is_array($data)) {
            return $headerValue;
        }

        return $service->parser()->extractExternalId($data, $headerValue);
    }

    /**
     * Check if async retries are enabled.
     */
    private function shouldRetryAsync(): bool
    {
        return config('larawebhook.retries.enabled', true)
            && config('larawebhook.retries.async', false);
    }

    /**
     * Dispatch a retry job for failed webhook validation.
     */
    private function dispatchRetryJob(
        string $payload,
        string $signature,
        string $service,
        string $event,
        string $secret,
        ?string $externalId
    ): void {
        $delays = config('larawebhook.retries.delays', [1, 5, 10]);
        $firstDelay = $delays[0] ?? 1;

        RetryWebhookJob::dispatch(
            $payload,
            $signature,
            $service,
            $event,
            $secret,
            1, // Start at attempt 1 (0 was the initial failed attempt)
            $externalId
        )->delay(now()->addSeconds($firstDelay));
    }
}
