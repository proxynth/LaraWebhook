<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Proxynth\Larawebhook\Models\WebhookLog;

beforeEach(function () {
    // Set up test secrets in config
    config([
        'larawebhook.services.stripe.webhook_secret' => 'test_stripe_secret',
        'larawebhook.services.github.webhook_secret' => 'test_github_secret',
        'larawebhook.services.slack.webhook_secret' => 'test_slack_secret',
        'larawebhook.services.shopify.webhook_secret' => 'test_shopify_secret',
    ]);

    // Register test routes with middleware
    Route::post('test-stripe-webhook', function () {
        return response()->json(['status' => 'success']);
    })->middleware('validate-webhook:stripe');

    Route::post('test-github-webhook', function () {
        return response()->json(['status' => 'success']);
    })->middleware('validate-webhook:github');

    Route::post('test-slack-webhook', function () {
        return response()->json(['status' => 'success']);
    })->middleware('validate-webhook:slack');

    Route::post('test-shopify-webhook', function () {
        return response()->json(['status' => 'success']);
    })->middleware('validate-webhook:shopify');
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
            ->and($log->event)->toBe('unknown') // No event field, so just 'unknown' action
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
            ->and($log->event)->toBe('opened') // No event field, so just 'opened' action
            ->and($log->status)->toBe('success');
    });
});

describe('ValidateWebhook middleware with unsupported service', function () {
    beforeEach(function () {
        // Register a route with an unsupported service
        Route::post('test-unsupported-webhook', function () {
            return response()->json(['status' => 'success']);
        })->middleware('validate-webhook:paypal');
    });

    it('rejects unsupported service with 400 error', function () {
        $payload = '{"event": "payment.completed"}';

        $response = $this->call(
            'POST',
            'test-unsupported-webhook',
            [],
            [],
            [],
            ['HTTP_X_PAYPAL_SIGNATURE' => 'some-signature', 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(400);
        expect($response->getContent())->toContain('Service paypal is not supported');
    });

    it('does not log anything for unsupported service', function () {
        $initialCount = WebhookLog::count();
        $payload = '{"event": "payment.completed"}';

        $this->call(
            'POST',
            'test-unsupported-webhook',
            [],
            [],
            [],
            ['HTTP_X_PAYPAL_SIGNATURE' => 'some-signature', 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        expect(WebhookLog::count())->toBe($initialCount);
    });
});

describe('ValidateWebhook middleware with Slack', function () {
    it('allows valid Slack webhooks', function () {
        $payload = '{"type": "event_callback", "event": {"type": "app_mention"}}';
        $timestamp = time();
        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, 'test_slack_secret');

        $response = $this->call(
            'POST',
            'test-slack-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SLACK_SIGNATURE' => $signature,
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $log = WebhookLog::latest()->first();
        expect($log->service)->toBe('slack')
            ->and($log->event)->toBe('app_mention')
            ->and($log->status)->toBe('success');
    });

    it('rejects Slack webhooks with invalid signature', function () {
        $payload = '{"type": "event_callback", "event": {"type": "app_mention"}}';
        $timestamp = time();

        $response = $this->call(
            'POST',
            'test-slack-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SLACK_SIGNATURE' => 'v0=invalid_signature',
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(403);

        $log = WebhookLog::latest()->first();
        expect($log->status)->toBe('failed');
    });

    it('rejects Slack webhooks with expired timestamp', function () {
        $payload = '{"type": "event_callback", "event": {"type": "app_mention"}}';
        $timestamp = time() - 400; // Expired
        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, 'test_slack_secret');

        $response = $this->call(
            'POST',
            'test-slack-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SLACK_SIGNATURE' => $signature,
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(400);
        expect($response->getContent())->toContain('expired');
    });

    it('rejects Slack webhooks with missing signature header', function () {
        $payload = '{"type": "event_callback"}';

        $response = $this->call(
            'POST',
            'test-slack-webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(400);
        expect($response->getContent())->toContain('Missing X-Slack-Signature');
    });

    it('rejects Slack webhooks with missing timestamp header', function () {
        // This test covers line 84 of ValidateWebhook.php
        // When Slack signature is present but timestamp header is missing,
        // the signature is passed without timestamp prefix, causing format error
        $payload = '{"type": "event_callback", "event": {"type": "app_mention"}}';
        $signature = 'v0='.hash_hmac('sha256', "v0:12345:{$payload}", 'test_slack_secret');

        $response = $this->call(
            'POST',
            'test-slack-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SLACK_SIGNATURE' => $signature,
                // Missing HTTP_X_SLACK_REQUEST_TIMESTAMP
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        // Without timestamp, validator receives just "v0=hash" instead of "timestamp:v0=hash"
        // This causes invalid format error
        $response->assertStatus(400);
        expect($response->getContent())->toContain('format');
    });

    it('extracts slash_command event type', function () {
        $payload = '{"command": "/remind", "text": "me to test"}';
        $timestamp = time();
        $sigBaseString = "v0:{$timestamp}:{$payload}";
        $signature = 'v0='.hash_hmac('sha256', $sigBaseString, 'test_slack_secret');

        $response = $this->call(
            'POST',
            'test-slack-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SLACK_SIGNATURE' => $signature,
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertOk();

        $log = WebhookLog::latest()->first();
        expect($log->event)->toBe('slash_command');
    });
});

describe('ValidateWebhook middleware with Shopify', function () {
    it('allows valid Shopify webhooks', function () {
        $payload = '{"id": 123456789, "email": "customer@example.com", "total_price": "99.99"}';
        $signature = base64_encode(hash_hmac('sha256', $payload, 'test_shopify_secret', true));

        $response = $this->call(
            'POST',
            'test-shopify-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
                'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $log = WebhookLog::latest()->first();
        expect($log->service)->toBe('shopify')
            ->and($log->status)->toBe('success');
    });

    it('rejects Shopify webhooks with invalid signature', function () {
        $payload = '{"id": 123456789}';
        $signature = base64_encode(hash_hmac('sha256', $payload, 'wrong_secret', true));

        $response = $this->call(
            'POST',
            'test-shopify-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(403);

        $log = WebhookLog::latest()->first();
        expect($log->status)->toBe('failed');
    });

    it('rejects Shopify webhooks with missing signature header', function () {
        $payload = '{"id": 123456789}';

        $response = $this->call(
            'POST',
            'test-shopify-webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(400);
        expect($response->getContent())->toContain('Missing X-Shopify-Hmac-Sha256');
    });

    it('validates order payload correctly', function () {
        $payload = json_encode([
            'id' => 820982911946154508,
            'email' => 'jon@example.com',
            'total_price' => '99.00',
            'currency' => 'USD',
            'financial_status' => 'paid',
        ]);
        $signature = base64_encode(hash_hmac('sha256', $payload, 'test_shopify_secret', true));

        $response = $this->call(
            'POST',
            'test-shopify-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response->assertOk();

        $log = WebhookLog::latest()->first();
        expect($log->status)->toBe('success');
    });

    it('rejects tampered Shopify payload', function () {
        $originalPayload = '{"id":123,"amount":"100.00"}';
        $signature = base64_encode(hash_hmac('sha256', $originalPayload, 'test_shopify_secret', true));

        $tamperedPayload = '{"id":123,"amount":"1000.00"}';

        $response = $this->call(
            'POST',
            'test-shopify-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $tamperedPayload
        );

        $response->assertStatus(403);
    });
});

describe('ValidateWebhook middleware idempotency', function () {
    it('rejects duplicate Stripe webhooks', function () {
        $payload = '{"id": "evt_test_duplicate", "type": "payment_intent.succeeded"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, 'test_stripe_secret');
        $signatureHeader = "t={$timestamp},v1={$signature}";

        // First request should succeed
        $response1 = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response1->assertOk()
            ->assertJson(['status' => 'success']);

        expect(WebhookLog::count())->toBe(1);

        // Second request with same external_id should be rejected
        $response2 = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response2->assertOk()
            ->assertJson([
                'status' => 'already_processed',
                'external_id' => 'evt_test_duplicate',
            ]);

        // No new log should be created
        expect(WebhookLog::count())->toBe(1);
    });

    it('rejects duplicate GitHub webhooks via header', function () {
        $payload = '{"action": "opened", "number": 1}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_github_secret');
        $deliveryId = 'github-delivery-123';

        // First request
        $response1 = $this->call(
            'POST',
            'test-github-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'HTTP_X_GITHUB_DELIVERY' => $deliveryId,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response1->assertOk();
        expect(WebhookLog::count())->toBe(1);

        // Second request with same delivery ID
        $response2 = $this->call(
            'POST',
            'test-github-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'HTTP_X_GITHUB_DELIVERY' => $deliveryId,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $response2->assertOk()
            ->assertJson([
                'status' => 'already_processed',
                'external_id' => $deliveryId,
            ]);

        expect(WebhookLog::count())->toBe(1);
    });

    it('allows same external_id for different services', function () {
        $stripePayload = '{"id": "shared_id_123", "type": "payment_intent.succeeded"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$stripePayload}";
        $stripeSignature = hash_hmac('sha256', $signedPayload, 'test_stripe_secret');
        $stripeSignatureHeader = "t={$timestamp},v1={$stripeSignature}";

        // Stripe webhook
        $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $stripeSignatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $stripePayload
        )->assertOk();

        // Slack webhook with same external_id in payload
        $slackPayload = '{"event_id": "shared_id_123", "type": "event_callback", "event": {"type": "app_mention"}}';
        $slackTimestamp = (string) time();
        $slackSignature = 'v0='.hash_hmac('sha256', "v0:{$slackTimestamp}:{$slackPayload}", 'test_slack_secret');

        $this->call(
            'POST',
            'test-slack-webhook',
            [],
            [],
            [],
            [
                'HTTP_X_SLACK_SIGNATURE' => $slackSignature,
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $slackTimestamp,
                'CONTENT_TYPE' => 'application/json',
            ],
            $slackPayload
        )->assertOk()->assertJson(['status' => 'success']);

        // Both should be logged (different services)
        expect(WebhookLog::count())->toBe(2);
    });

    it('processes webhook without external_id normally', function () {
        // Payload without id field
        $payload = '{"type": "unknown_event"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, 'test_stripe_secret');
        $signatureHeader = "t={$timestamp},v1={$signature}";

        // First request
        $response1 = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response1->assertOk();

        // Second request - should also succeed (no external_id to check)
        $response2 = $this->call(
            'POST',
            'test-stripe-webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signatureHeader, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response2->assertOk()->assertJson(['status' => 'success']);

        // Both should be logged
        expect(WebhookLog::count())->toBe(2);
    });
});
