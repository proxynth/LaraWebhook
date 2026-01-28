<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Parsers\SlackPayloadParser;

describe('SlackPayloadParser', function () {
    beforeEach(function () {
        $this->parser = new SlackPayloadParser;
    });

    it('implements PayloadParserInterface', function () {
        expect($this->parser)->toBeInstanceOf(PayloadParserInterface::class);
    });

    it('returns slack as service name', function () {
        expect($this->parser->serviceName())->toBe('slack');
    });
});

describe('SlackPayloadParser extractEventType', function () {
    beforeEach(function () {
        $this->parser = new SlackPayloadParser;
    });

    it('extracts event type from Event API callback', function () {
        $data = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'app_mention',
                'user' => 'U123',
            ],
        ];

        expect($this->parser->extractEventType($data))->toBe('app_mention');
    });

    it('extracts event type from message event', function () {
        $data = [
            'type' => 'event_callback',
            'event' => [
                'type' => 'message',
                'channel' => 'C123',
            ],
        ];

        expect($this->parser->extractEventType($data))->toBe('message');
    });

    it('extracts type from interactive components', function () {
        $data = [
            'type' => 'block_actions',
            'actions' => [
                ['action_id' => 'button_click'],
            ],
        ];

        expect($this->parser->extractEventType($data))->toBe('block_actions');
    });

    it('extracts type from view submission', function () {
        $data = [
            'type' => 'view_submission',
            'view' => ['id' => 'V123'],
        ];

        expect($this->parser->extractEventType($data))->toBe('view_submission');
    });

    it('extracts slash_command for slash commands', function () {
        $data = [
            'command' => '/remind',
            'text' => 'me to do something',
        ];

        expect($this->parser->extractEventType($data))->toBe('slash_command');
    });

    it('returns unknown for empty array', function () {
        expect($this->parser->extractEventType([]))->toBe('unknown');
    });

    it('returns unknown when no type info available', function () {
        $data = ['some_key' => 'some_value'];

        expect($this->parser->extractEventType($data))->toBe('unknown');
    });
});

describe('SlackPayloadParser extractMetadata', function () {
    beforeEach(function () {
        $this->parser = new SlackPayloadParser;
    });

    it('extracts full metadata from Event API payload', function () {
        $data = [
            'team_id' => 'T123',
            'api_app_id' => 'A456',
            'event_id' => 'Ev789',
            'event' => [
                'type' => 'app_mention',
                'user' => 'U111',
                'channel' => 'C222',
            ],
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata)->toBe([
            'team_id' => 'T123',
            'api_app_id' => 'A456',
            'event_id' => 'Ev789',
            'event_type' => 'app_mention',
            'user_id' => 'U111',
            'channel_id' => 'C222',
        ]);
    });

    it('extracts team_id from nested team object', function () {
        $data = [
            'team' => ['id' => 'T999'],
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['team_id'])->toBe('T999');
    });

    it('extracts user_id from nested user object', function () {
        $data = [
            'user' => ['id' => 'U888'],
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['user_id'])->toBe('U888');
    });

    it('extracts channel_id from nested channel object', function () {
        $data = [
            'channel' => ['id' => 'C777'],
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['channel_id'])->toBe('C777');
    });

    it('handles empty payload', function () {
        $metadata = $this->parser->extractMetadata([]);

        expect($metadata)->toBe([
            'team_id' => null,
            'api_app_id' => null,
            'event_id' => null,
            'event_type' => null,
            'user_id' => null,
            'channel_id' => null,
        ]);
    });

    it('extracts event_type from interactive component', function () {
        $data = [
            'type' => 'block_actions',
            'team' => ['id' => 'T123'],
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['event_type'])->toBe('block_actions');
    });
});
