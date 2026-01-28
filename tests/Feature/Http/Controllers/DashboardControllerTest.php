<?php

beforeEach(function () {
    // Set app key required for web middleware (sessions/encryption)
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    config([
        'larawebhook.services' => [
            'stripe' => ['webhook_secret' => 'test_stripe_secret'],
            'github' => ['webhook_secret' => 'test_github_secret'],
        ],
    ]);
});

it('displays the dashboard page', function () {
    $response = $this->get('/larawebhook/dashboard');

    $response->assertOk()
        ->assertViewIs('larawebhook::dashboard');
});

it('passes configured services to the view', function () {
    $response = $this->get('/larawebhook/dashboard');

    $response->assertOk()
        ->assertViewHas('services', ['stripe', 'github']);
});

it('displays service names in the filter dropdown', function () {
    $response = $this->get('/larawebhook/dashboard');

    $response->assertOk()
        ->assertSee('Stripe')
        ->assertSee('Github');
});

it('handles empty services configuration', function () {
    config(['larawebhook.services' => []]);

    $response = $this->get('/larawebhook/dashboard');

    $response->assertOk()
        ->assertViewHas('services', []);
});

it('renders the dashboard with correct page title', function () {
    $response = $this->get('/larawebhook/dashboard');

    $response->assertOk()
        ->assertSee('LaraWebhook Dashboard');
});

it('includes required UI elements', function () {
    $response = $this->get('/larawebhook/dashboard');

    $response->assertOk()
        ->assertSee('Filters')
        ->assertSee('Service')
        ->assertSee('Status')
        ->assertSee('Date')
        ->assertSee('Per Page');
});

it('can be accessed via named route', function () {
    $response = $this->get(route('larawebhook.dashboard'));

    $response->assertOk()
        ->assertViewIs('larawebhook::dashboard');
});
