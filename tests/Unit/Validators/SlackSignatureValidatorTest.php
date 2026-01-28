<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Validators\SlackSignatureValidator;

describe('SlackSignatureValidator', function () {
    beforeEach(function () {
        $this->validator = new SlackSignatureValidator;
        $this->secret = 'slack_signing_secret';
    });

    it('implements SignatureValidatorInterface', function () {
        expect($this->validator)->toBeInstanceOf(SignatureValidatorInterface::class);
    });

    it('returns slack as service name', function () {
        expect($this->validator->serviceName())->toBe('slack');
    });
});

describe('SlackSignatureValidator validate', function () {
    beforeEach(function () {
        $this->validator = new SlackSignatureValidator;
        $this->secret = 'slack_signing_secret';
    });

    it('validates correct signature', function () {
        $payload = '{"event": "app_mention"}';
        $timestamp = time();

        // Slack signature format: v0:timestamp:body
        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->secret);

        // Combined format: "timestamp:v0=hash"
        $combinedSignature = "{$timestamp}:{$signature}";

        $result = $this->validator->validate($payload, $combinedSignature, $this->secret);

        expect($result)->toBeTrue();
    });

    it('throws exception for missing timestamp', function () {
        $payload = '{"event": "test"}';
        $signature = 'v0=abc123';

        expect(fn () => $this->validator->validate($payload, $signature, $this->secret))
            ->toThrow(WebhookException::class, 'Invalid Slack signature format');
    });

    it('throws exception for expired timestamp', function () {
        $payload = '{"event": "test"}';
        $timestamp = time() - 400; // 400 seconds ago

        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->secret);
        $combinedSignature = "{$timestamp}:{$signature}";

        expect(fn () => $this->validator->validate($payload, $combinedSignature, $this->secret, 300))
            ->toThrow(WebhookException::class, 'Webhook is expired');
    });

    it('throws exception for future timestamp beyond tolerance', function () {
        $payload = '{"event": "test"}';
        $timestamp = time() + 400; // 400 seconds in future

        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->secret);
        $combinedSignature = "{$timestamp}:{$signature}";

        expect(fn () => $this->validator->validate($payload, $combinedSignature, $this->secret, 300))
            ->toThrow(WebhookException::class, 'Webhook is expired');
    });

    it('throws exception for missing v0 prefix', function () {
        $payload = '{"event": "test"}';
        $timestamp = time();
        $combinedSignature = "{$timestamp}:invalid_signature";

        expect(fn () => $this->validator->validate($payload, $combinedSignature, $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid Slack signature format');
    });

    it('throws exception for invalid signature', function () {
        $payload = '{"event": "test"}';
        $timestamp = time();
        $combinedSignature = "{$timestamp}:v0=invalid_hash_value_that_does_not_match";

        expect(fn () => $this->validator->validate($payload, $combinedSignature, $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid Slack webhook signature.');
    });

    it('respects custom tolerance', function () {
        $payload = '{"event": "test"}';
        $timestamp = time() - 500; // 500 seconds ago

        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->secret);
        $combinedSignature = "{$timestamp}:{$signature}";

        // With 600s tolerance, should pass
        $result = $this->validator->validate($payload, $combinedSignature, $this->secret, 600);

        expect($result)->toBeTrue();
    });

    it('validates with complex JSON payload', function () {
        $payload = '{"type":"event_callback","event":{"type":"app_mention","user":"U123","text":"hello"}}';
        $timestamp = time();

        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, $this->secret);
        $combinedSignature = "{$timestamp}:{$signature}";

        $result = $this->validator->validate($payload, $combinedSignature, $this->secret);

        expect($result)->toBeTrue();
    });
});
