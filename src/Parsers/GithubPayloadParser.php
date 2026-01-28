<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Parsers;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;

/**
 * Parser for GitHub webhook payloads.
 *
 * @see https://docs.github.com/en/webhooks
 */
class GithubPayloadParser implements PayloadParserInterface
{
    /**
     * Extract the event type from a GitHub webhook payload.
     *
     * GitHub uses "action" and contextual event data.
     * Format: "{action}.{event}" or just "{action}" if no event.
     *
     * @param  array<string, mixed>  $data
     */
    public function extractEventType(array $data): string
    {
        $action = $data['action'] ?? 'unknown';
        $event = $data['event'] ?? null;

        if ($event !== null) {
            return "{$action}.{$event}";
        }

        return $action;
    }

    /**
     * Extract relevant metadata from a GitHub webhook payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extractMetadata(array $data): array
    {
        return [
            'delivery_id' => $data['delivery'] ?? null,
            'action' => $data['action'] ?? null,
            'sender' => $data['sender']['login'] ?? null,
            'repository' => $data['repository']['full_name'] ?? null,
            'organization' => $data['organization']['login'] ?? null,
        ];
    }

    /**
     * Get the service name this parser handles.
     */
    public function serviceName(): string
    {
        return 'github';
    }
}
