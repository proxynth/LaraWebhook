<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Models\WebhookLog;
use Proxynth\Larawebhook\Services\WebhookValidator;

beforeEach(function () {
    $this->secret = 'test_secret_key_123';
    $this->validator = new WebhookValidator($this->secret, 300);
});

describe('validateAndLog with Stripe webhooks', function () {
    it('logs successful Stripe webhook validation', function () {
        $payload = '{"event": "payment_intent.succeeded", "id": "pi_123"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$timestamp},v1={$computedSignature}";

        $log = $this->validator->validateAndLog(
            $payload,
            $signatureHeader,
            'stripe',
            'payment_intent.succeeded'
        );

        expect($log)->toBeInstanceOf(WebhookLog::class)
            ->and($log->service)->toBe('stripe')
            ->and($log->event)->toBe('payment_intent.succeeded')
            ->and($log->status)->toBe('success')
            ->and($log->payload['event'])->toBe('payment_intent.succeeded')
            ->and($log->payload['id'])->toBe('pi_123')
            ->and($log->error_message)->toBeNull();

        // Verify log is persisted in database
        expect(WebhookLog::count())->toBe(1);
    });

    it('logs failed Stripe webhook validation with invalid signature', function () {
        $payload = '{"event": "payment_intent.succeeded"}';
        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef";

        $log = $this->validator->validateAndLog(
            $payload,
            $signatureHeader,
            'stripe',
            'payment_intent.succeeded'
        );

        expect($log->service)->toBe('stripe')
            ->and($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Invalid Stripe webhook signature');

        // Verify log is persisted
        expect(WebhookLog::failed()->count())->toBe(1);
    });

    it('logs failed Stripe webhook with expired timestamp', function () {
        $payload = '{"event": "test"}';
        $expiredTimestamp = time() - 400;
        $signedPayload = "{$expiredTimestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$expiredTimestamp},v1={$computedSignature}";

        $log = $this->validator->validateAndLog(
            $payload,
            $signatureHeader,
            'stripe',
            'test.event'
        );

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Webhook is expired');
    });

    it('logs failed Stripe webhook with malformed signature', function () {
        $payload = '{"event": "test"}';
        $signatureHeader = 'malformed_signature';

        $log = $this->validator->validateAndLog(
            $payload,
            $signatureHeader,
            'stripe',
            'test.event'
        );

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Invalid Stripe signature format');
    });
});

describe('validateAndLog with GitHub webhooks', function () {
    it('logs successful GitHub webhook validation', function () {
        $payload = '{"action": "opened", "pull_request": {"id": 123}}';
        $computedSignature = hash_hmac('sha256', $payload, $this->secret);
        $signatureHeader = "sha256={$computedSignature}";

        $log = $this->validator->validateAndLog(
            $payload,
            $signatureHeader,
            'github',
            'pull_request.opened'
        );

        expect($log->service)->toBe('github')
            ->and($log->event)->toBe('pull_request.opened')
            ->and($log->status)->toBe('success')
            ->and($log->payload['action'])->toBe('opened')
            ->and($log->error_message)->toBeNull();
    });

    it('logs failed GitHub webhook validation with invalid signature', function () {
        $payload = '{"action": "opened"}';
        $signatureHeader = 'sha256=invalid_hash_value';

        $log = $this->validator->validateAndLog(
            $payload,
            $signatureHeader,
            'github',
            'pull_request.opened'
        );

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Invalid GitHub webhook signature');
    });

    it('logs failed GitHub webhook with malformed signature header', function () {
        $payload = '{"action": "opened"}';
        $signatureHeader = 'sha1=some_hash';

        $log = $this->validator->validateAndLog(
            $payload,
            $signatureHeader,
            'github',
            'pull_request.opened'
        );

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Invalid GitHub signature format');
    });
});

describe('validateAndLog with invalid JSON payload', function () {
    it('handles non-JSON payload gracefully', function () {
        $payload = 'not valid json {{{';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $this->secret);
        $signatureHeader = "t={$timestamp},v1={$computedSignature}";

        $log = $this->validator->validateAndLog(
            $payload,
            $signatureHeader,
            'stripe',
            'test.event'
        );

        expect($log->status)->toBe('success')
            ->and($log->payload)->toHaveKey('raw')
            ->and($log->payload['raw'])->toBe($payload);
    });
});

describe('validateAndLog database persistence', function () {
    it('persists multiple webhook logs', function () {
        $payload1 = '{"event": "event1"}';
        $timestamp1 = time();
        $signedPayload1 = "{$timestamp1}.{$payload1}";
        $signature1 = "t={$timestamp1},v1=".hash_hmac('sha256', $signedPayload1, $this->secret);

        $payload2 = '{"event": "event2"}';
        $timestamp2 = time();
        $signedPayload2 = "{$timestamp2}.{$payload2}";
        $signature2 = "t={$timestamp2},v1=".hash_hmac('sha256', $signedPayload2, $this->secret);

        $this->validator->validateAndLog($payload1, $signature1, 'stripe', 'event1');
        $this->validator->validateAndLog($payload2, $signature2, 'stripe', 'event2');

        expect(WebhookLog::count())->toBe(2)
            ->and(WebhookLog::successful()->count())->toBe(2);
    });

    it('can query logs by service after validation', function () {
        $stripePayload = '{"event": "stripe_event"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$stripePayload}";
        $stripeSignature = "t={$timestamp},v1=".hash_hmac('sha256', $signedPayload, $this->secret);

        $githubPayload = '{"action": "github_event"}';
        $githubSignature = 'sha256='.hash_hmac('sha256', $githubPayload, $this->secret);

        $this->validator->validateAndLog($stripePayload, $stripeSignature, 'stripe', 'test.stripe');
        $this->validator->validateAndLog($githubPayload, $githubSignature, 'github', 'test.github');

        $stripeLogs = WebhookLog::service('stripe')->get();
        $githubLogs = WebhookLog::service('github')->get();

        expect($stripeLogs)->toHaveCount(1)
            ->and($githubLogs)->toHaveCount(1);
    });
});

describe('validateAndLog with unsupported service', function () {
    it('logs failure for unsupported service', function () {
        $payload = '{"event": "test"}';
        $signature = 'some_signature';

        $log = $this->validator->validateAndLog(
            $payload,
            $signature,
            'unsupported_service',
            'test.event'
        );

        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Unsupported service: unsupported_service');
    });
});
