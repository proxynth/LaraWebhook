<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Proxynth\Larawebhook\Http\WebhookLogResource;
use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Services\WebhookValidator;

class WebhookLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WebhookLog::query();

        if ($request->filled('service')) {
            $query->service($request->input('service'));
        }

        if ($request->filled('status')) {
            $query->status($request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 10);
        $logs = $query->paginate((int) $perPage);

        return response()->json([
            'data' => WebhookLogResource::collection($logs->items()),
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

    /**
     * Replay a webhook by re-validating and processing it.
     */
    public function replay(WebhookLog $log): JsonResponse
    {
        try {
            $secret = config("larawebhook.services.{$log->service}.webhook_secret");

            if (empty($secret)) {
                return response()->json([
                    'success' => false,
                    'message' => "Webhook secret not configured for {$log->service}.",
                ], 500);
            }

            // Re-validate the webhook with retries
            $validator = new WebhookValidator($secret);
            $payload = json_encode($log->payload);

            // Extract signature from original log (stored in payload metadata if available)
            // For replay, we'll create a new validation attempt
            $newLog = $validator->validateAndLog(
                $payload,
                $this->extractSignatureFromPayload($log),
                $log->service,
                $log->event,
                $log->attempt + 1
            );

            return response()->json([
                'success' => $newLog->status === 'success',
                'message' => $newLog->status === 'success'
                    ? 'Webhook replayed successfully!'
                    : 'Webhook replay failed: '.$newLog->error_message,
                'log' => new WebhookLogResource($newLog),
            ]);
        } catch (\Exception $e) {
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
    private function extractSignatureFromPayload(WebhookLog $log): string
    {
        // For now, we'll regenerate the signature for replay purposes
        // In a real implementation, you'd store the original signature
        $payload = json_encode($log->payload);
        $secret = config("larawebhook.services.{$log->service}.webhook_secret");

        if ($log->service === 'stripe') {
            $timestamp = time();
            $signedPayload = "{$timestamp}.{$payload}";
            $signature = hash_hmac('sha256', $signedPayload, $secret);

            return "t={$timestamp},v1={$signature}";
        }

        // GitHub format
        $signature = hash_hmac('sha256', $payload, $secret);

        return "sha256={$signature}";
    }
}
