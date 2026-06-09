<?php

use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Services\WebhookValidator;
use Proxynth\Larawebhook\Services\WebhookValidatorFactory;

it('resolves one validator per service', function () {
    config()->set('larawebhook.services.stripe.webhook_secret', 'stripe-secret');
    config()->set('larawebhook.services.github.webhook_secret', 'github-secret');

    $factory = app(WebhookValidatorFactory::class);

    $stripeValidator = $factory->forService('stripe');
    $githubValidator = $factory->forService('github');
    $stripeValidatorAgain = $factory->forService('stripe');

    expect($stripeValidator)
        ->toBeInstanceOf(WebhookValidator::class)
        ->and($githubValidator)
        ->toBeInstanceOf(WebhookValidator::class)
        ->and($stripeValidator)
        ->toBe($stripeValidatorAgain)
        ->and($stripeValidator)
        ->not->toBe($githubValidator);
});

it('does not reuse validator instances across services with different secrets', function () {
    config()->set('larawebhook.services.stripe.webhook_secret', 'stripe-secret');
    config()->set('larawebhook.services.github.webhook_secret', 'github-secret');

    $factory = app(WebhookValidatorFactory::class);

    expect($factory->forService('stripe'))
        ->not->toBe($factory->forService('github'));
});

it('throws when service secret is missing', function () {

    config()->set('larawebhook.services.stripe.webhook_secret', null);

    app(WebhookValidatorFactory::class)->forService('stripe');
})->throws(WebhookException::class, 'No secret configured for service: stripe');

it('throws when service is not supported', function () {
    config()->set('larawebhook.services.unknown.webhook_secret', null);

    app(WebhookValidatorFactory::class)->forService('unknown');
})->throws(WebhookException::class, "Webhook service 'unknown' is not supported");

it('can resolve stripe then github validators in the same process', function () {
    config()->set('larawebhook.services.stripe.webhook_secret', 'stripe-secret');
    config()->set('larawebhook.services.github.webhook_secret', 'github-secret');

    $factory = app(WebhookValidatorFactory::class);

    $stripeValidator = $factory->forService('stripe');
    $githubValidator = $factory->forService('github');

    expect($stripeValidator)->not->toBe($githubValidator);
});
