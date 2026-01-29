<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Parsers\StripePayloadParser;

describe('StripePayloadParser', function () {
    beforeEach(function () {
        $this->parser = new StripePayloadParser;
    });

    it('implements PayloadParserInterface', function () {
        expect($this->parser)->toBeInstanceOf(PayloadParserInterface::class);
    });

    it('returns stripe as service name', function () {
        expect($this->parser->serviceName())->toBe('stripe');
    });
});

describe('StripePayloadParser extractEventType', function () {
    beforeEach(function () {
        $this->parser = new StripePayloadParser;
    });

    it('extracts event type from type field', function () {
        $data = ['type' => 'payment_intent.succeeded'];

        expect($this->parser->extractEventType($data))->toBe('payment_intent.succeeded');
    });

    it('extracts charge.failed event type', function () {
        $data = ['type' => 'charge.failed'];

        expect($this->parser->extractEventType($data))->toBe('charge.failed');
    });

    it('extracts invoice.paid event type', function () {
        $data = ['type' => 'invoice.paid'];

        expect($this->parser->extractEventType($data))->toBe('invoice.paid');
    });

    it('returns unknown when type is missing', function () {
        $data = ['id' => 'evt_123'];

        expect($this->parser->extractEventType($data))->toBe('unknown');
    });

    it('returns unknown for empty array', function () {
        expect($this->parser->extractEventType([]))->toBe('unknown');
    });
});

describe('StripePayloadParser extractMetadata', function () {
    beforeEach(function () {
        $this->parser = new StripePayloadParser;
    });

    it('extracts full metadata from complete payload', function () {
        $data = [
            'id' => 'evt_123abc',
            'api_version' => '2023-10-16',
            'livemode' => true,
            'data' => [
                'object' => [
                    'id' => 'pi_456def',
                    'object' => 'payment_intent',
                ],
            ],
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata)->toBe([
            'event_id' => 'evt_123abc',
            'api_version' => '2023-10-16',
            'livemode' => true,
            'object_id' => 'pi_456def',
            'object_type' => 'payment_intent',
        ]);
    });

    it('handles missing event id', function () {
        $data = ['api_version' => '2023-10-16'];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['event_id'])->toBeNull();
    });

    it('handles missing api version', function () {
        $data = ['id' => 'evt_123'];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['api_version'])->toBeNull();
    });

    it('handles missing livemode', function () {
        $data = [];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['livemode'])->toBeNull();
    });

    it('handles missing data.object', function () {
        $data = ['id' => 'evt_123', 'data' => []];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['object_id'])->toBeNull()
            ->and($metadata['object_type'])->toBeNull();
    });

    it('handles empty payload', function () {
        $metadata = $this->parser->extractMetadata([]);

        expect($metadata)->toBe([
            'event_id' => null,
            'api_version' => null,
            'livemode' => null,
            'object_id' => null,
            'object_type' => null,
        ]);
    });

    it('extracts test mode livemode correctly', function () {
        $data = ['livemode' => false];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['livemode'])->toBeFalse();
    });
});

describe('StripePayloadParser extractExternalId', function () {
    beforeEach(function () {
        $this->parser = new StripePayloadParser;
    });

    it('extracts external id from payload id field', function () {
        $data = ['id' => 'evt_1234567890abcdef'];

        expect($this->parser->extractExternalId($data))->toBe('evt_1234567890abcdef');
    });

    it('returns null when id is missing', function () {
        $data = ['type' => 'payment_intent.succeeded'];

        expect($this->parser->extractExternalId($data))->toBeNull();
    });

    it('returns null for empty payload', function () {
        expect($this->parser->extractExternalId([]))->toBeNull();
    });

    it('ignores header value since Stripe uses payload', function () {
        $data = ['id' => 'evt_from_payload'];

        expect($this->parser->extractExternalId($data, 'ignored_header_value'))->toBe('evt_from_payload');
    });
});
