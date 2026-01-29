<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Parsers\ShopifyPayloadParser;

describe('ShopifyPayloadParser', function () {
    beforeEach(function () {
        $this->parser = new ShopifyPayloadParser;
    });

    it('implements PayloadParserInterface', function () {
        expect($this->parser)->toBeInstanceOf(PayloadParserInterface::class);
    });

    it('returns shopify as service name', function () {
        expect($this->parser->serviceName())->toBe('shopify');
    });
});

describe('ShopifyPayloadParser extractEventType', function () {
    beforeEach(function () {
        $this->parser = new ShopifyPayloadParser;
    });

    it('extracts topic from _topic field', function () {
        $data = [
            '_topic' => 'orders/create',
            'id' => 123456789,
        ];

        expect($this->parser->extractEventType($data))->toBe('orders/create');
    });

    it('extracts topic from topic field', function () {
        $data = [
            'topic' => 'products/update',
            'id' => 987654321,
        ];

        expect($this->parser->extractEventType($data))->toBe('products/update');
    });

    it('prefers _topic over topic', function () {
        $data = [
            '_topic' => 'orders/create',
            'topic' => 'orders/updated',
        ];

        expect($this->parser->extractEventType($data))->toBe('orders/create');
    });

    it('returns unknown when no topic available', function () {
        $data = ['id' => 123456789];

        expect($this->parser->extractEventType($data))->toBe('unknown');
    });

    it('returns unknown for empty array', function () {
        expect($this->parser->extractEventType([]))->toBe('unknown');
    });
});

describe('ShopifyPayloadParser extractMetadata', function () {
    beforeEach(function () {
        $this->parser = new ShopifyPayloadParser;
    });

    it('extracts full metadata from order payload', function () {
        $data = [
            'id' => 123456789,
            'admin_graphql_api_id' => 'gid://shopify/Order/123456789',
            '_shop_domain' => 'myshop.myshopify.com',
            'order_number' => 1001,
            'customer' => ['id' => 987654321],
            'total_price' => '99.99',
            'currency' => 'USD',
            'financial_status' => 'paid',
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata)->toBe([
            'id' => 123456789,
            'admin_graphql_api_id' => 'gid://shopify/Order/123456789',
            'shop_domain' => 'myshop.myshopify.com',
            'order_number' => 1001,
            'customer_id' => 987654321,
            'total_price' => '99.99',
            'currency' => 'USD',
            'financial_status' => 'paid',
        ]);
    });

    it('handles product payload', function () {
        $data = [
            'id' => 555555555,
            'admin_graphql_api_id' => 'gid://shopify/Product/555555555',
            'title' => 'Cool Product',
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['id'])->toBe(555555555)
            ->and($metadata['admin_graphql_api_id'])->toBe('gid://shopify/Product/555555555');
    });

    it('handles customer payload', function () {
        $data = [
            'id' => 444444444,
            'customer' => ['id' => 333333333],
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['id'])->toBe(444444444)
            ->and($metadata['customer_id'])->toBe(333333333);
    });

    it('handles empty payload', function () {
        $metadata = $this->parser->extractMetadata([]);

        expect($metadata)->toBe([
            'id' => null,
            'admin_graphql_api_id' => null,
            'shop_domain' => null,
            'order_number' => null,
            'customer_id' => null,
            'total_price' => null,
            'currency' => null,
            'financial_status' => null,
        ]);
    });

    it('handles missing customer object', function () {
        $data = ['id' => 123];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['customer_id'])->toBeNull();
    });

    it('handles null values', function () {
        $data = [
            'id' => null,
            'total_price' => null,
        ];

        $metadata = $this->parser->extractMetadata($data);

        expect($metadata['id'])->toBeNull()
            ->and($metadata['total_price'])->toBeNull();
    });
});

describe('ShopifyPayloadParser extractExternalId', function () {
    beforeEach(function () {
        $this->parser = new ShopifyPayloadParser;
    });

    it('extracts external id from header value', function () {
        $data = [];
        $headerValue = 'b54557e4-e9e0-4d5c-8e6b-9d2e7a8b1c3d';

        expect($this->parser->extractExternalId($data, $headerValue))->toBe('b54557e4-e9e0-4d5c-8e6b-9d2e7a8b1c3d');
    });

    it('falls back to _webhook_id in payload when header is null', function () {
        $data = ['_webhook_id' => 'webhook-id-from-payload'];

        expect($this->parser->extractExternalId($data, null))->toBe('webhook-id-from-payload');
    });

    it('prefers header value over payload _webhook_id', function () {
        $data = ['_webhook_id' => 'webhook-id-from-payload'];
        $headerValue = 'webhook-id-from-header';

        expect($this->parser->extractExternalId($data, $headerValue))->toBe('webhook-id-from-header');
    });

    it('returns null when both header and payload webhook_id are missing', function () {
        $data = ['id' => 123456789];

        expect($this->parser->extractExternalId($data, null))->toBeNull();
    });

    it('returns null for empty payload and null header', function () {
        expect($this->parser->extractExternalId([], null))->toBeNull();
    });
});
