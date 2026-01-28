<?php

use Proxynth\Larawebhook\Models\WebhookLog;

beforeEach(function () {
    config([
        'larawebhook.services.stripe.webhook_secret' => 'test_stripe_webhook_secret',
        'larawebhook.services.github.webhook_secret' => 'test_github_webhook_secret',
    ]);
});

it('returns paginated webhook logs', function () {
    WebhookLog::factory()->count(15)->create();

    $response = $this->getJson('/api/larawebhook/logs');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'service', 'event', 'status', 'payload', 'created_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ])->assertJsonCount(10, 'data');

    expect($response->json('meta.total'))->toBe(15);
});

it('filters logs by service', function () {
    WebhookLog::factory()->create(['service' => 'stripe']);
    WebhookLog::factory()->create(['service' => 'github']);
    WebhookLog::factory()->create(['service' => 'stripe']);

    $response = $this->getJson('/api/larawebhook/logs?service=stripe');

    $response->assertOk()
        ->assertJsonCount(2, 'data');

    foreach ($response->json('data') as $log) {
        expect($log['service'])->toBe('stripe');
    }
});

it('filters logs by status', function () {
    WebhookLog::factory()->create(['status' => 'success']);
    WebhookLog::factory()->create(['status' => 'failed']);
    WebhookLog::factory()->create(['status' => 'success']);

    $response = $this->getJson('/api/larawebhook/logs?status=failed');

    $response->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.status'))->toBe('failed');
});

it('filters logs by date', function () {
    $today = now()->format('Y-m-d');
    $yesterday = now()->subDay()->format('Y-m-d');

    WebhookLog::factory()->create(['created_at' => $today]);
    WebhookLog::factory()->create(['created_at' => $yesterday]);

    $response = $this->getJson('/api/larawebhook/logs?date='.$today);

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('applies multiple filters together', function () {
    $today = now()->format('Y-m-d');

    WebhookLog::factory()->create([
        'service' => 'stripe',
        'status' => 'success',
        'created_at' => $today,
    ]);

    WebhookLog::factory()->create([
        'service' => 'github',
        'status' => 'success',
        'created_at' => $today,
    ]);

    WebhookLog::factory()->create([
        'service' => 'stripe',
        'status' => 'failed',
        'created_at' => $today,
    ]);

    $response = $this->getJson('/api/larawebhook/logs?service=stripe&status=success&date='.$today);

    $response->assertOk()
        ->assertJsonCount(1, 'data');

    $log = $response->json('data.0');
    expect($log['service'])->toBe('stripe');
    expect($log['status'])->toBe('success');
});

it('respects custom per_page parameter', function () {
    WebhookLog::factory()->count(30)->create();

    $response = $this->getJson('/api/larawebhook/logs?per_page=5');

    $response->assertOk()
        ->assertJsonCount(5, 'data');

    expect($response->json('meta.per_page'))->toBe(5);
    expect($response->json('meta.total'))->toBe(30);
});

it('orders logs by most recent first', function () {
    $old = WebhookLog::factory()->create(['created_at' => now()->subDays(2)]);
    $recent = WebhookLog::factory()->create(['created_at' => now()]);
    $medium = WebhookLog::factory()->create(['created_at' => now()->subDay()]);

    $response = $this->getJson('/api/larawebhook/logs');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids)->toBe([$recent->id, $medium->id, $old->id]);
});

it('successfully replays a webhook', function () {
    $log = WebhookLog::factory()->create([
        'service' => 'stripe',
        'event' => 'payment_intent.succeeded',
        'payload' => ['type' => 'payment_intent.succeeded', 'data' => ['amount' => 1000]],
        'status' => 'failed',
        'attempt' => 0,
    ]);

    $response = $this->postJson("/api/larawebhook/logs/{$log->id}/replay");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Webhook replayed successfully!',
        ])
        ->assertJsonStructure([
            'log' => ['id', 'service', 'event', 'status', 'attempt'],
        ]);

    // Verify a new log entry was created
    expect(WebhookLog::count())->toBe(2);

    $newLog = WebhookLog::latest()->first();
    expect($newLog->attempt)->toBe(1);
});

it('returns error when replaying webhook without configured secret', function () {
    config(['larawebhook.services.stripe.webhook_secret' => null]);

    $log = WebhookLog::factory()->create([
        'service' => 'stripe',
        'event' => 'payment_intent.succeeded',
    ]);

    $response = $this->postJson("/api/larawebhook/logs/{$log->id}/replay");

    $response->assertStatus(500)
        ->assertJson([
            'success' => false,
            'message' => 'Webhook secret not configured for stripe.',
        ]);
});

it('handles replay failure gracefully', function () {
    $log = WebhookLog::factory()->create([
        'service' => 'github',
        'event' => 'push',
        'payload' => ['ref' => 'refs/heads/main'],
        'status' => 'failed',
    ]);

    $response = $this->postJson("/api/larawebhook/logs/{$log->id}/replay");

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'log',
        ]);
});

it('returns 404 for non-existent webhook log', function () {
    $response = $this->postJson('/api/larawebhook/logs/99999/replay');

    $response->assertNotFound();
});

it('returns empty data when no logs exist', function () {
    $response = $this->getJson('/api/larawebhook/logs');

    $response->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJson([
            'meta' => ['total' => 0],
        ]);
});

it('catches and returns error when exception occurs during replay', function () {
    $log = WebhookLog::factory()->create([
        'service' => 'stripe',
        'event' => 'payment_intent.succeeded',
        'payload' => ['type' => 'payment_intent.succeeded'],
        'status' => 'failed',
    ]);

    // Register an event listener that throws an exception when creating a new WebhookLog
    // This simulates a database error or other unexpected failure during replay
    WebhookLog::creating(function () {
        throw new \RuntimeException('Database connection lost');
    });

    $response = $this->postJson("/api/larawebhook/logs/{$log->id}/replay");

    $response->assertStatus(500)
        ->assertJson([
            'success' => false,
        ]);

    expect($response->json('message'))->toContain('Error replaying webhook:');
    expect($response->json('message'))->toContain('Database connection lost');
});
