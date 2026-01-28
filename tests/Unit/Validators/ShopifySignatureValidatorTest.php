<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Validators\ShopifySignatureValidator;

describe('ShopifySignatureValidator', function () {
    beforeEach(function () {
        $this->validator = new ShopifySignatureValidator;
        $this->secret = 'shopify_shared_secret';
    });

    it('implements SignatureValidatorInterface', function () {
        expect($this->validator)->toBeInstanceOf(SignatureValidatorInterface::class);
    });

    it('returns shopify as service name', function () {
        expect($this->validator->serviceName())->toBe('shopify');
    });
});

describe('ShopifySignatureValidator validate', function () {
    beforeEach(function () {
        $this->validator = new ShopifySignatureValidator;
        $this->secret = 'shopify_shared_secret';
    });

    it('validates correct signature', function () {
        $payload = '{"id":123456789,"email":"customer@example.com"}';

        // Shopify uses Base64-encoded HMAC-SHA256
        $signature = base64_encode(hash_hmac('sha256', $payload, $this->secret, true));

        $result = $this->validator->validate($payload, $signature, $this->secret);

        expect($result)->toBeTrue();
    });

    it('throws exception for empty signature', function () {
        $payload = '{"id": 123}';

        expect(fn () => $this->validator->validate($payload, '', $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Missing Shopify signature.');
    });

    it('throws exception for invalid signature', function () {
        $payload = '{"id": 123}';
        $signature = 'invalid_base64_signature';

        expect(fn () => $this->validator->validate($payload, $signature, $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid Shopify webhook signature.');
    });

    it('throws exception for wrong secret', function () {
        $payload = '{"id": 123}';
        $signature = base64_encode(hash_hmac('sha256', $payload, 'wrong_secret', true));

        expect(fn () => $this->validator->validate($payload, $signature, $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid Shopify webhook signature.');
    });

    it('ignores tolerance parameter', function () {
        $payload = '{"id": 123}';
        $signature = base64_encode(hash_hmac('sha256', $payload, $this->secret, true));

        // Tolerance should not affect Shopify validation
        $result = $this->validator->validate($payload, $signature, $this->secret, 0);

        expect($result)->toBeTrue();
    });

    it('validates with complex JSON payload', function () {
        $payload = '{"id":123456789,"order_number":1001,"total_price":"99.99","customer":{"id":987654321,"email":"test@example.com"}}';
        $signature = base64_encode(hash_hmac('sha256', $payload, $this->secret, true));

        $result = $this->validator->validate($payload, $signature, $this->secret);

        expect($result)->toBeTrue();
    });

    it('validates order create webhook payload', function () {
        $payload = json_encode([
            'id' => 820982911946154508,
            'email' => 'jon@example.com',
            'closed_at' => null,
            'created_at' => '2024-01-15T10:30:00-05:00',
            'updated_at' => '2024-01-15T10:30:00-05:00',
            'number' => 234,
            'total_price' => '99.00',
            'currency' => 'USD',
            'financial_status' => 'paid',
        ]);

        $signature = base64_encode(hash_hmac('sha256', $payload, $this->secret, true));

        $result = $this->validator->validate($payload, $signature, $this->secret);

        expect($result)->toBeTrue();
    });

    it('throws exception for tampered payload', function () {
        $originalPayload = '{"id":123,"amount":"100.00"}';
        $signature = base64_encode(hash_hmac('sha256', $originalPayload, $this->secret, true));

        $tamperedPayload = '{"id":123,"amount":"1000.00"}';

        expect(fn () => $this->validator->validate($tamperedPayload, $signature, $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid Shopify webhook signature.');
    });
});
