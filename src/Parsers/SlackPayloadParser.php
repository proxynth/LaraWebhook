<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Parsers;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;

/**
 * Parser for Slack webhook payloads.
 *
 * Slack sends various event types including:
 * - Event API callbacks (app_mention, message, etc.)
 * - Interactive components (button clicks, modals)
 * - Slash commands
 *
 * @see https://api.slack.com/events
 * @see https://api.slack.com/interactivity/handling
 */
class SlackPayloadParser implements PayloadParserInterface
{
    /**
     * Extract the event type from a Slack webhook payload.
     *
     * Slack uses different structures depending on the event type:
     * - Event API: { "type": "event_callback", "event": { "type": "app_mention" } }
     * - Interactive: { "type": "block_actions", "actions": [...] }
     * - Slash commands: { "command": "/remind" }
     *
     * @param  array<string, mixed>  $data
     */
    public function extractEventType(array $data): string
    {
        // Event API callback
        if (isset($data['event']['type'])) {
            return $data['event']['type'];
        }

        // Interactive components (block_actions, view_submission, etc.)
        if (isset($data['type'])) {
            return $data['type'];
        }

        // Slash commands
        if (isset($data['command'])) {
            return 'slash_command';
        }

        return 'unknown';
    }

    /**
     * Extract metadata from a Slack webhook payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extractMetadata(array $data): array
    {
        return [
            'team_id' => $data['team_id'] ?? $data['team']['id'] ?? null,
            'api_app_id' => $data['api_app_id'] ?? null,
            'event_id' => $data['event_id'] ?? null,
            'event_type' => $data['event']['type'] ?? $data['type'] ?? null,
            'user_id' => $data['event']['user'] ?? $data['user']['id'] ?? null,
            'channel_id' => $data['event']['channel'] ?? $data['channel']['id'] ?? null,
        ];
    }

    /**
     * Get the service name this parser handles.
     */
    public function serviceName(): string
    {
        return 'slack';
    }
}
