<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Exceptions\WebhookException;
use Proxynth\Larawebhook\Shared\Domain\Enums\WebhookService;

describe('WebhookService enum cases', function () {
    it('has Stripe case', function () {
        expect(WebhookService::Stripe->value)->toBe('stripe');
    });

    it('has Github case', function () {
        expect(WebhookService::Github->value)->toBe('github');
    });

    it('has exactly 4 cases', function () {
        expect(WebhookService::cases())->toHaveCount(4);
    });

    it('has Slack case', function () {
        expect(WebhookService::Slack->value)->toBe('slack');
    });

    it('has Shopify case', function () {
        expect(WebhookService::Shopify->value)->toBe('shopify');
    });
});

describe('WebhookService isSupported', function () {
    it('returns true for supported services', function () {
        expect(WebhookService::isSupported('stripe'))->toBeTrue()
            ->and(WebhookService::isSupported('github'))->toBeTrue()
            ->and(WebhookService::isSupported('slack'))->toBeTrue()
            ->and(WebhookService::isSupported('shopify'))->toBeTrue();
    });

    it('returns false for unsupported services', function () {
        expect(WebhookService::isSupported('unknown'))->toBeFalse()
            ->and(WebhookService::isSupported('paypal'))->toBeFalse()
            ->and(WebhookService::isSupported(''))->toBeFalse();
    });

    it('is case sensitive', function () {
        expect(WebhookService::isSupported('Stripe'))->toBeFalse()
            ->and(WebhookService::isSupported('GITHUB'))->toBeFalse();
    });
});

describe('WebhookService fromString', function () {
    it('returns Stripe enum from string', function () {
        expect(WebhookService::fromString('stripe'))->toBe(WebhookService::Stripe);
    });

    it('returns Github enum from string', function () {
        expect(WebhookService::fromString('github'))->toBe(WebhookService::Github);
    });

    it('throws for invalid service', function () {
        expect(fn () => WebhookService::fromString('invalid'))
            ->toThrow(WebhookException::class, "Webhook service 'invalid' is not supported.");
    });
});

describe('WebhookService tryFromString', function () {
    it('returns Stripe enum from string', function () {
        expect(WebhookService::tryFromString('stripe'))->toBe(WebhookService::Stripe);
    });

    it('returns Github enum from string', function () {
        expect(WebhookService::tryFromString('github'))->toBe(WebhookService::Github);
    });

    it('returns null for invalid service', function () {
        expect(WebhookService::tryFromString('invalid'))->toBeNull()
            ->and(WebhookService::tryFromString(''))->toBeNull();
    });
});

describe('WebhookService values', function () {
    it('returns all service values as strings', function () {
        expect(WebhookService::values())->toBe(['stripe', 'github', 'slack', 'shopify']);
    });
});

describe('WebhookService validationRule', function () {
    it('returns values for use in validation rules', function () {
        expect(WebhookService::validationRule())->toBe(['stripe', 'github', 'slack', 'shopify']);
    });

    it('can be used with Laravel in rule', function () {
        $rule = 'in:'.implode(',', WebhookService::validationRule());

        expect($rule)->toBe('in:stripe,github,slack,shopify');
    });
});

describe('WebhookService usage patterns', function () {
    it('can be used in match expressions', function () {
        $service = WebhookService::Stripe;

        $result = match ($service) {
            WebhookService::Stripe => 'stripe_handler',
            WebhookService::Github => 'github_handler',
            WebhookService::Slack => 'slack_handler',
            WebhookService::Shopify => 'shopify_handler',
        };

        expect($result)->toBe('stripe_handler');
    });

    it('can be compared with equality', function () {
        $service = WebhookService::Github;

        expect($service === WebhookService::Github)->toBeTrue()
            ->and($service === WebhookService::Stripe)->toBeFalse()
            ->and($service === WebhookService::Slack)->toBeFalse()
            ->and($service === WebhookService::Shopify)->toBeFalse();
    });

    it('can be used as array key', function () {
        $handlers = [
            WebhookService::Stripe->value => 'StripeHandler',
            WebhookService::Github->value => 'GithubHandler',
            WebhookService::Slack->value => 'SlackHandler',
            WebhookService::Shopify->value => 'ShopifyHandler',
        ];

        expect($handlers['stripe'])->toBe('StripeHandler')
            ->and($handlers['github'])->toBe('GithubHandler')
            ->and($handlers['slack'])->toBe('SlackHandler')
            ->and($handlers['shopify'])->toBe('ShopifyHandler');
    });
});
