# Extending LaraWebhook

LaraWebhook uses the **Strategy Pattern** for maximum extensibility. Add support for any webhook provider in minutes.

## Architecture

```
src/
├── Contracts/
│   ├── PayloadParserInterface.php        # Strategy for parsing
│   └── SignatureValidatorInterface.php   # Strategy for validation
├── Parsers/
│   ├── StripePayloadParser.php
│   ├── GithubPayloadParser.php
│   ├── SlackPayloadParser.php
│   └── ShopifyPayloadParser.php
├── Validators/
│   ├── StripeSignatureValidator.php
│   ├── GithubSignatureValidator.php
│   ├── SlackSignatureValidator.php
│   └── ShopifySignatureValidator.php
└── Enums/
    └── WebhookService.php                # Central delegation
```

## Adding a New Service

### Step 1: Create the Payload Parser

```php
// src/Parsers/PaypalPayloadParser.php
<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Parsers;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;

class PaypalPayloadParser implements PayloadParserInterface
{
    public function extractEventType(array $data): string
    {
        return $data['event_type'] ?? 'unknown';
    }

    public function extractMetadata(array $data): array
    {
        return [
            'event_id' => $data['id'] ?? null,
            'resource_type' => $data['resource_type'] ?? null,
            'summary' => $data['summary'] ?? null,
        ];
    }

    public function serviceName(): string
    {
        return 'paypal';
    }
}
```

### Step 2: Create the Signature Validator

```php
// src/Validators/PaypalSignatureValidator.php
<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Validators;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;

class PaypalSignatureValidator implements SignatureValidatorInterface
{
    public function validate(
        string $payload, 
        string $signature, 
        string $secret, 
        int $tolerance = 300
    ): bool {
        // PayPal uses base64-encoded HMAC-SHA256
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if (! hash_equals($expected, $signature)) {
            throw new InvalidSignatureException('Invalid PayPal webhook signature.');
        }

        return true;
    }

    public function serviceName(): string
    {
        return 'paypal';
    }
}
```

### Step 3: Register in the Enum

```php
// src/Enums/WebhookService.php

use Proxynth\Larawebhook\Parsers\PaypalPayloadParser;
use Proxynth\Larawebhook\Validators\PaypalSignatureValidator;

enum WebhookService: string
{
    case Stripe = 'stripe';
    case Github = 'github';
    case Slack = 'slack';
    case Shopify = 'shopify';
    case Paypal = 'paypal';  // Add new case

    public function parser(): PayloadParserInterface
    {
        return match ($this) {
            self::Stripe => new StripePayloadParser,
            self::Github => new GithubPayloadParser,
            self::Slack => new SlackPayloadParser,
            self::Shopify => new ShopifyPayloadParser,
            self::Paypal => new PaypalPayloadParser,  // Add mapping
        };
    }

    public function signatureValidator(): SignatureValidatorInterface
    {
        return match ($this) {
            self::Stripe => new StripeSignatureValidator,
            self::Github => new GithubSignatureValidator,
            self::Slack => new SlackSignatureValidator,
            self::Shopify => new ShopifySignatureValidator,
            self::Paypal => new PaypalSignatureValidator,  // Add mapping
        };
    }

    public function signatureHeader(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe-Signature',
            self::Github => 'X-Hub-Signature-256',
            self::Slack => 'X-Slack-Signature',
            self::Shopify => 'X-Shopify-Hmac-Sha256',
            self::Paypal => 'PAYPAL-TRANSMISSION-SIG',  // Add header
        };
    }
}
```

### Step 4: Add Configuration

```php
// config/larawebhook.php
'services' => [
    // ... existing services
    'paypal' => [
        'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
],
```

### Step 5: Use It!

```php
// routes/web.php
Route::post('/paypal-webhook', [PaypalController::class, 'handle'])
    ->middleware('validate-webhook:paypal');

// Or with the facade
Larawebhook::validate($payload, $signature, WebhookService::Paypal);
```

## Interfaces Reference

### PayloadParserInterface

```php
interface PayloadParserInterface
{
    /**
     * Extract the event type from the webhook payload.
     */
    public function extractEventType(array $data): string;

    /**
     * Extract metadata from the webhook payload.
     * 
     * @return array<string, mixed>
     */
    public function extractMetadata(array $data): array;

    /**
     * Get the service name this parser handles.
     */
    public function serviceName(): string;
}
```

### SignatureValidatorInterface

```php
interface SignatureValidatorInterface
{
    /**
     * Validate the webhook signature.
     *
     * @throws InvalidSignatureException
     * @throws WebhookException
     */
    public function validate(
        string $payload,
        string $signature,
        string $secret,
        int $tolerance = 300
    ): bool;

    /**
     * Get the service name this validator handles.
     */
    public function serviceName(): string;
}
```

## Using Parsers Directly

```php
use Proxynth\Larawebhook\Enums\WebhookService;

$payload = json_decode($request->getContent(), true);

// Extract event type
$eventType = WebhookService::Stripe->parser()->extractEventType($payload);
// Returns: 'payment_intent.succeeded'

// Extract metadata
$metadata = WebhookService::Github->parser()->extractMetadata($payload);
// Returns: ['delivery_id' => '...', 'action' => 'opened', ...]
```

## Using Validators Directly

```php
use Proxynth\Larawebhook\Enums\WebhookService;

$isValid = WebhookService::Stripe->signatureValidator()->validate(
    payload: $rawPayload,
    signature: $signatureHeader,
    secret: config('larawebhook.services.stripe.webhook_secret'),
    tolerance: 300
);
```

## Common Signature Algorithms

| Provider | Algorithm | Format |
|----------|-----------|--------|
| Stripe | HMAC-SHA256 | `t=timestamp,v1=signature` |
| GitHub | HMAC-SHA256 | `sha256=signature` |
| Slack | HMAC-SHA256 | `v0=signature` (with timestamp) |
| Shopify | HMAC-SHA256 | Base64 encoded |
| PayPal | Various | Certificate or HMAC |
| Twilio | HMAC-SHA1 | `signature` |
| SendGrid | ECDSA | `signature` + `timestamp` |

## Testing Your Implementation

```php
// tests/Unit/Parsers/PaypalPayloadParserTest.php
describe('PaypalPayloadParser', function () {
    it('extracts event type', function () {
        $parser = new PaypalPayloadParser();
        $data = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'];

        expect($parser->extractEventType($data))
            ->toBe('PAYMENT.CAPTURE.COMPLETED');
    });

    it('returns unknown for missing event type', function () {
        $parser = new PaypalPayloadParser();

        expect($parser->extractEventType([]))->toBe('unknown');
    });
});

// tests/Unit/Validators/PaypalSignatureValidatorTest.php
describe('PaypalSignatureValidator', function () {
    it('validates correct signature', function () {
        $validator = new PaypalSignatureValidator();
        $payload = '{"event_type": "test"}';
        $secret = 'test_secret';
        $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        expect($validator->validate($payload, $signature, $secret))
            ->toBeTrue();
    });

    it('throws on invalid signature', function () {
        $validator = new PaypalSignatureValidator();

        expect(fn () => $validator->validate('payload', 'invalid', 'secret'))
            ->toThrow(InvalidSignatureException::class);
    });
});
```
