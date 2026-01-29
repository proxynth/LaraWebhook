<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Parsers\GithubPayloadParser;
use Proxynth\Larawebhook\Parsers\ShopifyPayloadParser;
use Proxynth\Larawebhook\Parsers\SlackPayloadParser;
use Proxynth\Larawebhook\Parsers\StripePayloadParser;
use Proxynth\Larawebhook\Validators\GithubSignatureValidator;
use Proxynth\Larawebhook\Validators\ShopifySignatureValidator;
use Proxynth\Larawebhook\Validators\SlackSignatureValidator;
use Proxynth\Larawebhook\Validators\StripeSignatureValidator;

beforeEach(function () {
    config([
        'larawebhook.services.stripe.webhook_secret' => 'stripe_secret_123',
        'larawebhook.services.github.webhook_secret' => 'github_secret_456',
        'larawebhook.services.slack.webhook_secret' => 'slack_secret_789',
        'larawebhook.services.shopify.webhook_secret' => 'shopify_secret_abc',
    ]);
});

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

describe('WebhookService signatureHeader', function () {
    it('returns correct header for Stripe', function () {
        expect(WebhookService::Stripe->signatureHeader())->toBe('Stripe-Signature');
    });

    it('returns correct header for Github', function () {
        expect(WebhookService::Github->signatureHeader())->toBe('X-Hub-Signature-256');
    });

    it('returns correct header for Slack', function () {
        expect(WebhookService::Slack->signatureHeader())->toBe('X-Slack-Signature');
    });

    it('returns correct header for Shopify', function () {
        expect(WebhookService::Shopify->signatureHeader())->toBe('X-Shopify-Hmac-Sha256');
    });
});

describe('WebhookService timestampHeader', function () {
    it('returns timestamp header for Slack', function () {
        expect(WebhookService::Slack->timestampHeader())->toBe('X-Slack-Request-Timestamp');
    });

    it('returns null for services without timestamp header', function () {
        expect(WebhookService::Stripe->timestampHeader())->toBeNull()
            ->and(WebhookService::Github->timestampHeader())->toBeNull()
            ->and(WebhookService::Shopify->timestampHeader())->toBeNull();
    });
});

describe('WebhookService externalIdHeader', function () {
    it('returns X-GitHub-Delivery for GitHub', function () {
        expect(WebhookService::Github->externalIdHeader())->toBe('X-GitHub-Delivery');
    });

    it('returns X-Shopify-Webhook-Id for Shopify', function () {
        expect(WebhookService::Shopify->externalIdHeader())->toBe('X-Shopify-Webhook-Id');
    });

    it('returns null for Stripe (uses payload)', function () {
        expect(WebhookService::Stripe->externalIdHeader())->toBeNull();
    });

    it('returns null for Slack (uses payload)', function () {
        expect(WebhookService::Slack->externalIdHeader())->toBeNull();
    });
});

describe('WebhookService secretConfigKey', function () {
    it('returns correct config key for Stripe', function () {
        expect(WebhookService::Stripe->secretConfigKey())
            ->toBe('larawebhook.services.stripe.webhook_secret');
    });

    it('returns correct config key for Github', function () {
        expect(WebhookService::Github->secretConfigKey())
            ->toBe('larawebhook.services.github.webhook_secret');
    });

    it('returns correct config key for Slack', function () {
        expect(WebhookService::Slack->secretConfigKey())
            ->toBe('larawebhook.services.slack.webhook_secret');
    });

    it('returns correct config key for Shopify', function () {
        expect(WebhookService::Shopify->secretConfigKey())
            ->toBe('larawebhook.services.shopify.webhook_secret');
    });
});

describe('WebhookService secret', function () {
    it('returns secret from config for Stripe', function () {
        expect(WebhookService::Stripe->secret())->toBe('stripe_secret_123');
    });

    it('returns secret from config for Github', function () {
        expect(WebhookService::Github->secret())->toBe('github_secret_456');
    });

    it('returns secret from config for Slack', function () {
        expect(WebhookService::Slack->secret())->toBe('slack_secret_789');
    });

    it('returns secret from config for Shopify', function () {
        expect(WebhookService::Shopify->secret())->toBe('shopify_secret_abc');
    });

    it('returns null when secret is not configured', function () {
        config(['larawebhook.services.stripe.webhook_secret' => null]);

        expect(WebhookService::Stripe->secret())->toBeNull();
    });
});

describe('WebhookService isSupported', function () {
    it('returns true for stripe', function () {
        expect(WebhookService::isSupported('stripe'))->toBeTrue();
    });

    it('returns true for github', function () {
        expect(WebhookService::isSupported('github'))->toBeTrue();
    });

    it('returns true for slack', function () {
        expect(WebhookService::isSupported('slack'))->toBeTrue();
    });

    it('returns true for shopify', function () {
        expect(WebhookService::isSupported('shopify'))->toBeTrue();
    });

    it('returns false for unknown service', function () {
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

    it('throws ValueError for invalid service', function () {
        expect(fn () => WebhookService::fromString('invalid'))
            ->toThrow(ValueError::class);
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
        $values = WebhookService::values();

        expect($values)->toBeArray()
            ->and($values)->toContain('stripe')
            ->and($values)->toContain('github')
            ->and($values)->toContain('slack')
            ->and($values)->toContain('shopify')
            ->and($values)->toHaveCount(4);
    });
});

describe('WebhookService validationRule', function () {
    it('returns values for use in validation rules', function () {
        $rule = WebhookService::validationRule();

        expect($rule)->toBe(['stripe', 'github', 'slack', 'shopify']);
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

describe('WebhookService parser', function () {
    it('returns PayloadParserInterface for all services', function () {
        foreach (WebhookService::cases() as $service) {
            expect($service->parser())->toBeInstanceOf(PayloadParserInterface::class);
        }
    });

    it('returns StripePayloadParser for Stripe', function () {
        expect(WebhookService::Stripe->parser())->toBeInstanceOf(StripePayloadParser::class);
    });

    it('returns GithubPayloadParser for Github', function () {
        expect(WebhookService::Github->parser())->toBeInstanceOf(GithubPayloadParser::class);
    });

    it('returns SlackPayloadParser for Slack', function () {
        expect(WebhookService::Slack->parser())->toBeInstanceOf(SlackPayloadParser::class);
    });

    it('returns ShopifyPayloadParser for Shopify', function () {
        expect(WebhookService::Shopify->parser())->toBeInstanceOf(ShopifyPayloadParser::class);
    });

    it('parser service name matches enum value', function () {
        foreach (WebhookService::cases() as $service) {
            expect($service->parser()->serviceName())->toBe($service->value);
        }
    });

    it('returns new parser instance each time', function () {
        $parser1 = WebhookService::Stripe->parser();
        $parser2 = WebhookService::Stripe->parser();

        expect($parser1)->not->toBe($parser2);
    });

    it('can extract event type via parser', function () {
        $stripeData = ['type' => 'payment_intent.succeeded'];
        $githubData = ['action' => 'opened', 'event' => 'pull_request'];
        $slackData = ['event' => ['type' => 'app_mention']];
        $shopifyData = ['_topic' => 'orders/create'];

        expect(WebhookService::Stripe->parser()->extractEventType($stripeData))
            ->toBe('payment_intent.succeeded')
            ->and(WebhookService::Github->parser()->extractEventType($githubData))
            ->toBe('opened.pull_request')
            ->and(WebhookService::Slack->parser()->extractEventType($slackData))
            ->toBe('app_mention')
            ->and(WebhookService::Shopify->parser()->extractEventType($shopifyData))
            ->toBe('orders/create');
    });

    it('can extract metadata via parser', function () {
        $stripeData = ['id' => 'evt_123', 'livemode' => true];
        $githubData = ['action' => 'opened', 'sender' => ['login' => 'octocat']];
        $slackData = ['team_id' => 'T123', 'api_app_id' => 'A456'];
        $shopifyData = ['id' => 123456789, 'order_number' => 1001];

        $stripeMetadata = WebhookService::Stripe->parser()->extractMetadata($stripeData);
        $githubMetadata = WebhookService::Github->parser()->extractMetadata($githubData);
        $slackMetadata = WebhookService::Slack->parser()->extractMetadata($slackData);
        $shopifyMetadata = WebhookService::Shopify->parser()->extractMetadata($shopifyData);

        expect($stripeMetadata['event_id'])->toBe('evt_123')
            ->and($stripeMetadata['livemode'])->toBeTrue()
            ->and($githubMetadata['action'])->toBe('opened')
            ->and($githubMetadata['sender'])->toBe('octocat')
            ->and($slackMetadata['team_id'])->toBe('T123')
            ->and($slackMetadata['api_app_id'])->toBe('A456')
            ->and($shopifyMetadata['id'])->toBe(123456789)
            ->and($shopifyMetadata['order_number'])->toBe(1001);
    });
});

describe('WebhookService signatureValidator', function () {
    it('returns SignatureValidatorInterface for all services', function () {
        foreach (WebhookService::cases() as $service) {
            expect($service->signatureValidator())->toBeInstanceOf(SignatureValidatorInterface::class);
        }
    });

    it('returns StripeSignatureValidator for Stripe', function () {
        expect(WebhookService::Stripe->signatureValidator())->toBeInstanceOf(StripeSignatureValidator::class);
    });

    it('returns GithubSignatureValidator for Github', function () {
        expect(WebhookService::Github->signatureValidator())->toBeInstanceOf(GithubSignatureValidator::class);
    });

    it('returns SlackSignatureValidator for Slack', function () {
        expect(WebhookService::Slack->signatureValidator())->toBeInstanceOf(SlackSignatureValidator::class);
    });

    it('returns ShopifySignatureValidator for Shopify', function () {
        expect(WebhookService::Shopify->signatureValidator())->toBeInstanceOf(ShopifySignatureValidator::class);
    });

    it('validator service name matches enum value', function () {
        foreach (WebhookService::cases() as $service) {
            expect($service->signatureValidator()->serviceName())->toBe($service->value);
        }
    });

    it('returns new validator instance each time', function () {
        $validator1 = WebhookService::Stripe->signatureValidator();
        $validator2 = WebhookService::Stripe->signatureValidator();

        expect($validator1)->not->toBe($validator2);
    });

    it('can validate signature via validator', function () {
        $secret = 'test_secret';
        $payload = '{"type": "test"}';
        $timestamp = time();

        // Test Stripe
        $signedPayload = "{$timestamp}.{$payload}";
        $stripeSignature = "t={$timestamp},v1=".hash_hmac('sha256', $signedPayload, $secret);

        expect(WebhookService::Stripe->signatureValidator()->validate($payload, $stripeSignature, $secret))
            ->toBeTrue();

        // Test GitHub
        $githubSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        expect(WebhookService::Github->signatureValidator()->validate($payload, $githubSignature, $secret))
            ->toBeTrue();

        // Test Slack
        $slackSigBaseString = "v0:{$timestamp}:{$payload}";
        $slackSignature = "{$timestamp}:v0=".hash_hmac('sha256', $slackSigBaseString, $secret);

        expect(WebhookService::Slack->signatureValidator()->validate($payload, $slackSignature, $secret))
            ->toBeTrue();

        // Test Shopify
        $shopifySignature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        expect(WebhookService::Shopify->signatureValidator()->validate($payload, $shopifySignature, $secret))
            ->toBeTrue();
    });
});
