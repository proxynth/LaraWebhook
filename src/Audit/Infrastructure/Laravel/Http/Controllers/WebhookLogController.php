<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JsonException;
use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookLogDetails;
use Proxynth\Larawebhook\Audit\Application\Queries\GetWebhookLogDetailsQuery;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogs;
use Proxynth\Larawebhook\Audit\Application\Queries\ListWebhookLogsQuery;
use Proxynth\Larawebhook\Audit\Domain\Exceptions\PayloadNotAvailable;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Http\Resources\WebhookLogResource;
use Proxynth\Larawebhook\Audit\Infrastructure\Laravel\Persistence\Models\WebhookLog;
use Proxynth\Larawebhook\Ingestion\Application\Data\IncomingWebhookSignature;
use Proxynth\Larawebhook\Processing\Application\Commands\ReplayWebhookCommand;
use Proxynth\Larawebhook\Processing\Application\UseCases\ReplayWebhook;
use RuntimeException;
use Throwable;

class WebhookLogController extends Controller
{
    public function __construct(
        private readonly ListWebhookLogs $listWebhookLogs,
        private readonly GetWebhookLogDetails $getWebhookLogDetails,
        private readonly ReplayWebhook $replayWebhook,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $logs = $this->listWebhookLogs->handle(new ListWebhookLogsQuery(
            service: $this->nullableString($request, 'service'),
            status: $this->nullableString($request, 'status'),
            event: $this->nullableString($request, 'event'),
            date: $this->nullableString($request, 'date'),
            perPage: min(max($request->integer('per_page', 25), 1), 100),
        ));

        return response()->json([
            'data' => WebhookLogResource::collection($logs),
            'meta' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'prev' => $logs->previousPageUrl(),
                'next' => $logs->nextPageUrl(),
            ],
        ]);
    }

    public function show(WebhookLog $log): WebhookLogResource
    {
        $readModel = $this->getWebhookLogDetails->handle(
            new GetWebhookLogDetailsQuery($log->getKey())
        );

        return new WebhookLogResource($readModel);
    }

    /**
     * Replay a webhook by re-validating and processing it.
     */
    public function replay(WebhookLog $log): JsonResponse
    {
        try {
            $newLog = $this->replayWebhook->handle(new ReplayWebhookCommand(
                log: $log,
                signature: $this->extractSignatureFromPayload($log),
            ));

            return response()->json([
                'success' => $newLog->status === 'success',
                'message' => $newLog->status === 'success'
                    ? 'Webhook replayed successfully.'
                    : 'Webhook replay failed: '.$newLog->error_message,
                'log' => new WebhookLogResource($newLog),
            ]);
        } catch (PayloadNotAvailable) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot replay webhook because the payload was not stored for this log.',
                'reason' => 'payload_not_available',
            ], 422);
        } catch (JsonException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error encoding webhook payload for replay: '.$e->getMessage(),
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error replaying webhook: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract signature from original webhook payload.
     * This is a placeholder - in production, you'd store the original signature.
     */
    private function extractSignatureFromPayload(WebhookLog $log): IncomingWebhookSignature
    {
        // For now, we'll regenerate the signature for replay purposes
        // In a real implementation, you'd store the original signature
        $payload = json_encode($log->payload);
        $secret = config("larawebhook.services.{$log->service}.webhook_secret");

        if ($secret === null) {
            throw new RuntimeException('No secret configured for service: '.$log->service);
        }

        if ($log->service === 'stripe') {
            $timestamp = time();
            $signedPayload = "{$timestamp}.{$payload}";
            $signature = hash_hmac('sha256', $signedPayload, $secret);

            return new IncomingWebhookSignature("t={$timestamp},v1={$signature}");
        }

        // GitHub format
        $signature = hash_hmac('sha256', $payload, $secret);

        return new IncomingWebhookSignature("sha256={$signature}");
    }

    private function nullableString(Request $request, string $key): ?string
    {
        $value = $request->string($key)->trim()->toString();

        return $value === '' ? null : $value;
    }
}
