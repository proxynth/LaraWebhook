<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
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
        $payload = $request->getContent();
        $signatureHeader = $this->getSignatureHeader($service);
        $signature = $request->header($signatureHeader);

        if (empty($signature)) {
            return response("Missing {$signatureHeader} header.", Response::HTTP_BAD_REQUEST);
        }

        if (empty($payload)) {
            return response('Request body is empty.', Response::HTTP_BAD_REQUEST);
        }

        $event = $this->extractEventType($payload, $service);

        $secret = $this->getSecret($service);
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
     * Get the signature header name for the service.
     */
    private function getSignatureHeader(string $service): string
    {
        return match ($service) {
            'stripe' => 'Stripe-Signature',
            'github' => 'X-Hub-Signature-256',
            default => throw new \InvalidArgumentException("Service {$service} is not supported."),
        };
    }

    /**
     * Get the webhook secret from config for the service.
     */
    private function getSecret(string $service): ?string
    {
        return match ($service) {
            'stripe' => config('larawebhook.services.stripe.webhook_secret'),
            'github' => config('larawebhook.services.github.webhook_secret'),
            default => null,
        };
    }

    /**
     * Extract event type from the webhook payload.
     */
    private function extractEventType(string $payload, string $service): string
    {
        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return 'unknown';
        }

        return match ($service) {
            'stripe' => $data['type'] ?? 'unknown',
            'github' => ($data['action'] ?? 'unknown').'.'.($data['event'] ?? 'unknown'),
            default => 'unknown',
        };
    }
}
