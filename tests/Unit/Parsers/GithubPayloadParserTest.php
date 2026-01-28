<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Parsers\GithubPayloadParser;

describe('GithubPayloadParser', function () {
    beforeEach(function () {
        $this->parser = new GithubPayloadParser;
    });

    it('implements PayloadParserInterface', function () {
        expect($this->parser)->toBeInstanceOf(PayloadParserInterface::class);
    });

    it('returns github as service name', function () {
        expect($this->parser->serviceName())->toBe('github');
    });
});

describe('GithubPayloadParser extractEventType', function () {
    beforeEach(function () {
        $this->parser = new GithubPayloadParser;
    });

    it('extracts action with event', function () {
        $data = ['action' => 'opened', 'event' => 'pull_request'];

        expect($this->parser->extractEventType($data))->toBe('opened.pull_request');
    });

    it('extracts action only when no event', function () {
        $data = ['action' => 'created'];

        expect($this->parser->extractEventType($data))->toBe('created');
    });

    it('extracts closed action with event', function () {
        $data = ['action' => 'closed', 'event' => 'issue'];

        expect($this->parser->extractEventType($data))->toBe('closed.issue');
    });

    it('returns unknown.event when action is missing', function () {
        $data = ['event' => 'push'];

        // When action is missing but event exists, format is 'unknown.{event}'
        expect($this->parser->extractEventType($data))->toBe('unknown.push');
    });

    it('returns unknown for empty array', function () {
        expect($this->parser->extractEventType([]))->toBe('unknown');
    });

    it('handles push event format', function () {
        $data = ['action' => 'push'];

        expect($this->parser->extractEventType($data))->toBe('push');
    });

    it('handles synchronize action', function () {
        $data = ['action' => 'synchronize', 'event' => 'pull_request'];

        expect($this->parser->extractEventType($data))->toBe('synchronize.pull_request');
    });
});

describe('GithubPayloadParser extractMetadata', function () {
    beforeEach(function () {
        $this->parser = new GithubPayloadParser;
    });

    it('extracts full metadata from complete payload', function () {
        $data = [
            'delivery' => 'abc123-delivery-id',
            'action' => 'opened',
            'sender' => ['login' => 'octocat'],
            'repository' => ['full_name' => 'octocat/hello-world'],
            'organization' => ['login' => 'github'],
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata)->toBe([
            'delivery_id' => 'abc123-delivery-id',
            'action' => 'opened',
            'sender' => 'octocat',
            'repository' => 'octocat/hello-world',
            'organization' => 'github',
        ]);
    });

    it('handles missing delivery id', function () {
        $data = ['action' => 'opened'];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['delivery_id'])->toBeNull();
    });

    it('handles missing sender', function () {
        $data = ['action' => 'opened'];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['sender'])->toBeNull();
    });

    it('handles missing repository', function () {
        $data = ['action' => 'opened'];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['repository'])->toBeNull();
    });

    it('handles missing organization', function () {
        $data = ['action' => 'opened'];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['organization'])->toBeNull();
    });

    it('handles empty payload', function () {
        $metadata = $this->parser->extractMetadata([]);

        expect($metadata)->toBe([
            'delivery_id' => null,
            'action' => null,
            'sender' => null,
            'repository' => null,
            'organization' => null,
        ]);
    });

    it('handles sender object without login', function () {
        $data = ['sender' => ['id' => 123]];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['sender'])->toBeNull();
    });

    it('handles repository object without full_name', function () {
        $data = ['repository' => ['id' => 456]];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['repository'])->toBeNull();
    });
});
