<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Services\WebhookValidator;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhook
{
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

        if (empty($payload)) {
            return response('Request body is empty.', Response::HTTP_BAD_REQUEST);
        }

        $event = $this->extractEventType($payload, $webhookService);

        $secret = $webhookService->secret();
        if (empty($secret)) {
            return response("Webhook secret not configured for {$service}.", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $validator = new WebhookValidator($secret);
        $log = $validator->validateAndLog($payload, $signature, $service, $event);

        if ($log->status === 'failed') {
            $statusCode = str_contains($log->error_message, 'format') || str_contains($log->error_message, 'expired')
                ? Response::HTTP_BAD_REQUEST
                : Response::HTTP_FORBIDDEN;

            return response($log->error_message, $statusCode);
        }

        return $next($request);
    }

    /**
     * Extract event type from the webhook payload using the service's parser.
     */
    private function extractEventType(string $payload, WebhookService $service): string
    {
        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return 'unknown';
        }

        return $service->parser()->extractEventType($data);
    }
}
