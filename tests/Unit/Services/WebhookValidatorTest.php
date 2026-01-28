<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Services\WebhookValidator;

beforeEach(function () {
    $this->secret = 'test_secret_key_123';
    $this->webhookValidator = new WebhookValidator(
        secret: $this->secret,
        tolerance: 300
    );
});

describe('Stripe webhook validation', function () {
    it('validates correct Stripe signature', function () {
        $payload = '{"event": "payment_intent.succeeded"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$timestamp},v1={$computedSignature}";

        expect($this->webhookValidator->validate($payload, $signatureHeader, 'stripe'))->toBeTrue();
    });

    it('throws exception for invalid Stripe signature', function () {
        $payload = '{"event": "payment_intent.succeeded"}';
        $timestamp = time();

        $signatureHeader = "t={$timestamp},v1=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef";

        $this->webhookValidator->validate($payload, $signatureHeader, 'stripe');
    })->throws(InvalidSignatureException::class, 'Invalid Stripe webhook signature.');

    it('throws exception for expired Stripe webhook', function () {
        $payload = '{"event": "payment_intent.succeeded"}';
        $expiredTimestamp = time() - 400; // Expired beyond 300s tolerance
        $signedPayload = "{$expiredTimestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$expiredTimestamp},v1={$computedSignature}";

        $this->webhookValidator->validate($payload, $signatureHeader, 'stripe');
    })->throws(WebhookException::class, 'Webhook is expired');

    it('throws exception for malformed Stripe signature header', function () {
        $payload = '{"event": "test"}';
        $signatureHeader = 'malformed_header_without_timestamp';

        $this->webhookValidator->validate($payload, $signatureHeader, 'stripe');
    })->throws(WebhookException::class, 'Invalid Stripe signature format');

    it('validates Stripe webhook within tolerance window', function () {
        $payload = '{"event": "test"}';
        $timestamp = time() - 200; // Within 300s tolerance
        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$timestamp},v1={$computedSignature}";

        expect($this->webhookValidator->validate($payload, $signatureHeader, 'stripe'))
            ->toBeTrue();
    });
});

describe('GitHub webhook validation', function () {
    it('validates correct GitHub signature', function () {
        $payload = '{"action": "opened", "pull_request": {}}';
        $computedSignature = hash_hmac('sha256', $payload, $this->secret);
        $signatureHeader = "sha256={$computedSignature}";

        expect($this->webhookValidator->validate($payload, $signatureHeader, 'github'))
            ->toBeTrue();
    });

    it('throws exception for invalid GitHub signature', function () {
        $payload = '{"action": "opened"}';
        $signatureHeader = 'sha256=invalid_hash_value';

        $this->webhookValidator->validate($payload, $signatureHeader, 'github');
    })->throws(InvalidSignatureException::class, 'Invalid GitHub webhook signature');

    it('throws exception for malformed GitHub signature header', function () {
        $payload = '{"action": "opened"}';
        $signatureHeader = 'sha1=some_hash'; // Wrong algorithm

        $this->webhookValidator->validate($payload, $signatureHeader, 'github');
    })->throws(InvalidSignatureException::class, 'Invalid GitHub signature format');

    it('validates GitHub webhook with complex payload', function () {
        $payload = json_encode([
            'action' => 'opened',
            'pull_request' => [
                'id' => 123,
                'title' => 'Test PR',
                'body' => 'This is a test pull request',
            ],
        ]);
        $computedSignature = hash_hmac('sha256', $payload, $this->secret);
        $signatureHeader = "sha256={$computedSignature}";

        expect($this->webhookValidator->validate($payload, $signatureHeader, 'github'))
            ->toBeTrue();
    });
});

describe('Service validation', function () {
    it('throws exception for unsupported service', function () {
        $payload = '{"event": "test"}';
        $signature = 'some_signature';

        $this->webhookValidator->validate($payload, $signature, 'unsupported_service');
    })->throws(WebhookException::class, 'Unsupported service: unsupported_service');
});

describe('Tolerance configuration', function () {
    it('accepts custom tolerance value', function () {
        $customValidator = new WebhookValidator($this->secret, 600); // 10 minutes

        $payload = '{"event": "test"}';
        $timestamp = time() - 500; // 8 minutes ago, within 600s tolerance
        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$timestamp},v1={$computedSignature}";

        expect($customValidator->validate($payload, $signatureHeader, 'stripe'))
            ->toBeTrue();
    });

    it('rejects webhook beyond custom tolerance', function () {
        $customValidator = new WebhookValidator($this->secret, 60); // 1 minute

        $payload = '{"event": "test"}';
        $timestamp = time() - 120; // 2 minutes ago, beyond 60s tolerance
        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$timestamp},v1={$computedSignature}";

        $customValidator->validate($payload, $signatureHeader, 'stripe');
    })->throws(WebhookException::class, 'Webhook is expired');
});

describe('WebhookService enum support', function () {
    it('validates Stripe using enum', function () {
        $payload = '{"event": "payment_intent.succeeded"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$timestamp},v1={$computedSignature}";

        expect($this->webhookValidator->validate($payload, $signatureHeader, WebhookService::Stripe))
            ->toBeTrue();
    });

    it('validates GitHub using enum', function () {
        $payload = '{"action": "opened"}';
        $computedSignature = hash_hmac('sha256', $payload, $this->secret);
        $signatureHeader = "sha256={$computedSignature}";

        expect($this->webhookValidator->validate($payload, $signatureHeader, WebhookService::Github))
            ->toBeTrue();
    });

    it('throws exception for invalid signature using enum', function () {
        $payload = '{"action": "opened"}';
        $signatureHeader = 'sha256=invalid';

        $this->webhookValidator->validate($payload, $signatureHeader, WebhookService::Github);
    })->throws(InvalidSignatureException::class);

    it('accepts both string and enum interchangeably', function () {
        $payload = '{"action": "opened"}';
        $computedSignature = hash_hmac('sha256', $payload, $this->secret);
        $signatureHeader = "sha256={$computedSignature}";

        // Both should work identically
        $resultWithString = $this->webhookValidator->validate($payload, $signatureHeader, 'github');
        $resultWithEnum = $this->webhookValidator->validate($payload, $signatureHeader, WebhookService::Github);

        expect($resultWithString)->toBeTrue()
            ->and($resultWithEnum)->toBeTrue();
    });
});
