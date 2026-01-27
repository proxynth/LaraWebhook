# LaraWebhook 🚀

[![Latest Version](https://img.shields.io/packagist/v/proxynth/larawebhook.svg)](https://packagist.org/packages/proxynth/larawebhook)
[![Tests](https://github.com/proxynth/larawebhook/actions/workflows/tests.yml/badge.svg)](https://github.com/proxynth/larawebhook/actions)
[![codecov](https://codecov.io/github/proxynth/LaraWebhook/graph/badge.svg?token=4WGFTA8HDR)](https://codecov.io/github/proxynth/LaraWebhook)
[![PHPStan](https://github.com/proxynth/larawebhook/actions/workflows/phpstan.yml/badge.svg)](https://github.com/proxynth/larawebhook/actions)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**LaraWebhook** is an open-source Laravel package for handling incoming webhooks in a **secure, reliable, and simple** way. Validate signatures, manage retries, log events, and integrate popular services (Stripe, GitHub, Slack, etc.) in minutes.

---

## ✨ Features

- **Signature Validation**: Verify webhook authenticity (Stripe, GitHub, etc.)
- **Retry Management**: Automatically retry failed webhooks
- **Detailed Logging**: Store events and errors for debugging
- **Easy Integration**: Minimal configuration, compatible with Laravel 9+
- **Extensible**: Add your own validators or services

---

## 📦 Installation

1. Install the package via Composer:
   ```bash
   composer require proxynth/larawebhook
   ```

2. Publish the configuration:
   ```bash
   php artisan vendor:publish --provider="Proxynth\LaraWebhook\LaraWebhookServiceProvider"
   ```

3. Configure your signature keys in `config/larawebhook.php`:
   ```php
   'stripe' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300, // Tolerance in seconds
   ],
   ```

---

## 🛠 Usage

### Using the Middleware (Recommended)

The easiest way to validate webhooks is using the `validate-webhook` middleware:

```php
// routes/web.php
Route::post('/stripe-webhook', function () {
    // Webhook is automatically validated and logged
    // Process your webhook here
    $payload = json_decode(request()->getContent(), true);

    // Handle the event
    event(new \App\Events\StripeWebhookReceived($payload));

    return response()->json(['status' => 'success']);
})->middleware('validate-webhook:stripe');

Route::post('/github-webhook', function () {
    // Webhook is automatically validated and logged
    $payload = json_decode(request()->getContent(), true);

    // Handle the event
    event(new \App\Events\GithubWebhookReceived($payload));

    return response()->json(['status' => 'success']);
})->middleware('validate-webhook:github');
```

**What the middleware does:**
- ✅ Validates the webhook signature
- ✅ Automatically logs the event to the database
- ✅ Returns 403 for invalid signatures
- ✅ Returns 400 for missing headers or malformed payloads

### Manual Validation (Advanced)

For more control, you can manually validate webhooks:

```php
// app/Http/Controllers/WebhookController.php
use Proxynth\Larawebhook\Services\WebhookValidator;
use Illuminate\Http\Request;

public function handleWebhook(Request $request)
{
    $payload = $request->getContent();
    $signature = $request->header('Stripe-Signature');
    $secret = config('larawebhook.services.stripe.webhook_secret');

    $validator = new WebhookValidator($secret);

    try {
        // Validate and log in one call
        $log = $validator->validateAndLog(
            $payload,
            $signature,
            'stripe',
            'payment_intent.succeeded'
        );

        // Process the event
        event(new \App\Events\StripeWebhookReceived(json_decode($payload, true)));

        return response()->json(['status' => 'success']);
    } catch (\Exception $e) {
        return response($e->getMessage(), 403);
    }
}
```

---

## 🔧 Configuration

Modify `config/larawebhook.php` to:
* Add services (Stripe, GitHub, etc.)
* Configure validation tolerance
* Enable/disable logging

Example:
```php
'services' => [
    'stripe' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
    'github' => [
        'secret' => env('GITHUB_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
],
```

---

## 📊 Logging

Webhooks are logged in the `webhook_logs` table with:
* service (e.g., stripe, github)
* event (e.g., payment_intent.succeeded)
* status (success/failed)
* payload (webhook content)
* created_at

To view logs:
```bash
php artisan tinker
>>> \Proxynth\LaraWebhook\Models\WebhookLog::latest()->get();
```

---

## 🧪 Tests

Run tests with:
```bash
composer test
```

*(Tests cover validation, retries, and logging.)*

---

## 🚀 Release Process

This project uses [Release Please](https://github.com/googleapis/release-please) for automated releases and changelog management.

### How it works

1. **Commit with Conventional Commits format:**
   ```bash
   git commit -m "feat: add new webhook validation"
   git commit -m "fix: resolve signature verification bug"
   git commit -m "docs: update installation instructions"
   ```

2. **Release Please creates a PR automatically** when changes are pushed to `master`:
    - Generates/updates `CHANGELOG.md` based on commits
    - Bumps version in `.release-please-manifest.json`
    - Creates a release PR titled "chore(master): release X.Y.Z"

3. **Review and merge the release PR:**
    - Review the generated changelog
    - Merge the PR to trigger the release

4. **Automatic actions on merge:**
    - Creates a GitHub Release with tag `vX.Y.Z`
    - Runs tests and static analysis
    - Packagist syncs automatically (no manual webhook needed)

### Conventional Commits format

- `feat:` → New feature (bumps minor version)
- `fix:` → Bug fix (bumps patch version)
- `docs:` → Documentation changes
- `style:` → Code style changes (formatting, etc.)
- `refactor:` → Code refactoring
- `perf:` → Performance improvements
- `test:` → Adding/updating tests
- `chore:` → Maintenance tasks
- `ci:` → CI/CD changes

**Breaking changes:** Add `!` after type or add `BREAKING CHANGE:` in commit body to bump major version.

Example:
```bash
git commit -m "feat!: change webhook validation API"
```

---

## 🤝 Contributing

1. Fork the repository
2. Create a branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -am 'Add my feature'`)
4. Push the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

*(See CONTRIBUTING.md for more details.)*

---

## 📄 License

This project is licensed under the MIT License. See LICENSE for more information.
