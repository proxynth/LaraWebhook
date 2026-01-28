<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Proxynth\Larawebhook\Models\WebhookLog;

beforeEach(function () {
    // Set up test secrets in config
    config([
        'larawebhook.services.stripe.webhook_secret' => 'test_stripe_secret',
        'larawebhook.services.github.webhook_secret' => 'test_github_secret',
    ]);

    // Register test routes with middleware
    Route::post('test-stripe-webhook', function () {
        return response()->json(['status' => 'success']);
    })->middleware('validate-webhook:stripe');

    Route::post('test-github-webhook', function () {
        return response()->json(['status' => 'success']);
    })->middleware('validate-webhook:github');
});

describe('ValidateWebhook middleware with Stripe', function () {
    it('allows valid Stripe webhooks', function () {
        $payload = '{"type": "payment_intent.succeeded"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, 'test_stripe_secret');
        $signatureHeader = "t={$timestamp},v1={$signature}";

        $response = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        // Verify webhook was logged
        expect(WebhookLog::count())->toBe(1);
        $log = WebhookLog::first();
        expect($log->service)->toBe('stripe')
            ->and($log->event)->toBe('payment_intent.succeeded')
            ->and($log->status)->toBe('success');
    });

    it('rejects Stripe webhooks with missing signature', function () {
        $payload = '{"type": "payment_intent.succeeded"}';

        $response = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(400)
            ->assertSee('Missing Stripe-Signature header');
    });

    it('rejects Stripe webhooks with empty payload', function () {
        $signatureHeader = 't='.time().',v1=somesignature';

        $response = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader],
            ''
        );

        $response->assertStatus(400)
            ->assertSee('Request body is empty');
    });

    it('rejects Stripe webhooks with invalid signature', function () {
        $payload = '{"type": "payment_intent.succeeded"}';
        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef";

        $response = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(403)
            ->assertSee('Invalid Stripe webhook signature');

        // Verify failure was logged
        expect(WebhookLog::count())->toBe(1);
        $log = WebhookLog::first();
        expect($log->status)->toBe('failed')
            ->and($log->error_message)->toContain('Invalid Stripe webhook signature');
    });

    it('rejects Stripe webhooks with malformed signature', function () {
        $payload = '{"type": "payment_intent.succeeded"}';

        $response = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 'malformed_signature', 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(400)
            ->assertSee('Invalid Stripe signature format');
    });
});

describe('ValidateWebhook middleware with GitHub', function () {
    it('allows valid GitHub webhooks', function () {
        $payload = '{"action": "opened", "event": "pull_request"}';
        $signature = hash_hmac('sha256', $payload, 'test_github_secret');
        $signatureHeader = "sha256={$signature}";

        $response = $this->call(
            'POST',
            'test-github-webhook',
            [],
            [],
            [],
            ['HTTP_X_HUB_SIGNATURE_256' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        // Verify webhook was logged
        expect(WebhookLog::count())->toBe(1);
        $log = WebhookLog::first();
        expect($log->service)->toBe('github')
            ->and($log->event)->toBe('opened.pull_request')
            ->and($log->status)->toBe('success');
    });

    it('rejects GitHub webhooks with missing signature', function () {
        $payload = '{"action": "opened"}';

        $response = $this->call(
            'POST',
            'test-github-webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(400)
            ->assertSee('Missing X-Hub-Signature-256 header');
    });

    it('rejects GitHub webhooks with invalid signature', function () {
        $payload = '{"action": "opened"}';
        $signatureHeader = 'sha256=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

        $response = $this->call(
            'POST',
            'test-github-webhook',
            [],
            [],
            [],
            ['HTTP_X_HUB_SIGNATURE_256' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(403)
            ->assertSee('Invalid GitHub webhook signature');
    });
});

describe('ValidateWebhook middleware configuration', function () {
    it('returns 500 when secret is not configured', function () {
        config(['larawebhook.services.stripe.webhook_secret' => null]);

        $payload = '{"type": "test"}';
        $signatureHeader = 't='.time().',v1=somesignature';

        $response = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(500)
            ->assertSee('Webhook secret not configured for stripe');
    });
});

describe('ValidateWebhook middleware with invalid JSON payload', function () {
    it('extracts unknown event type when payload is not valid JSON for GitHub', function () {
        $payload = 'this is not valid json {{{';
        $signature = hash_hmac('sha256', $payload, 'test_github_secret');
        $signatureHeader = "sha256={$signature}";

        $response = $this->call(
            'POST',
            'test-github-webhook',
            [],
            [],
            [],
            ['HTTP_X_HUB_SIGNATURE_256' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        // The webhook should succeed (valid signature) but event should be 'unknown'
        $response->assertOk();

        $log = WebhookLog::latest()->first();
        expect($log->service)->toBe('github')
            ->and($log->event)->toBe('unknown')
            ->and($log->status)->toBe('success');
    });

    it('extracts unknown event type when payload is plain text for Stripe', function () {
        $payload = 'plain text payload';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, 'test_stripe_secret');
        $signatureHeader = "t={$timestamp},v1={$signature}";

        $response = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertOk();

        $log = WebhookLog::latest()->first();
        expect($log->service)->toBe('stripe')
            ->and($log->event)->toBe('unknown')
            ->and($log->status)->toBe('success');
    });

    it('extracts unknown event when JSON object has no type key for Stripe', function () {
        $payload = '{"data": "something", "other_key": "value"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, 'test_stripe_secret');
        $signatureHeader = "t={$timestamp},v1={$signature}";

        $response = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertOk();

        $log = WebhookLog::latest()->first();
        expect($log->service)->toBe('stripe')
            ->and($log->event)->toBe('unknown')
            ->and($log->status)->toBe('success');
    });

    it('extracts unknown.unknown event when GitHub JSON has no action or event keys', function () {
        $payload = '{"data": "something", "other_key": "value"}';
        $signature = hash_hmac('sha256', $payload, 'test_github_secret');
        $signatureHeader = "sha256={$signature}";

        $response = $this->call(
            'POST',
            'test-github-webhook',
            [],
            [],
            [],
            ['HTTP_X_HUB_SIGNATURE_256' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertOk();

        $log = WebhookLog::latest()->first();
        expect($log->service)->toBe('github')
            ->and($log->event)->toBe('unknown.unknown')
            ->and($log->status)->toBe('success');
    });

    it('extracts partial event when GitHub JSON has only action key', function () {
        $payload = '{"action": "opened"}';
        $signature = hash_hmac('sha256', $payload, 'test_github_secret');
        $signatureHeader = "sha256={$signature}";

        $response = $this->call(
            'POST',
            'test-github-webhook',
            [],
            [],
            [],
            ['HTTP_X_HUB_SIGNATURE_256' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertOk();

        $log = WebhookLog::latest()->first();
        expect($log->service)->toBe('github')
            ->and($log->event)->toBe('opened.unknown')
            ->and($log->status)->toBe('success');
    });
});
