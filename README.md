# LaraWebhook 🚀

[![Latest Version](https://img.shields.io/packagist/v/proxynth/larawebhook.svg)](https://packagist.org/packages/proxynth/larawebhook)
[![Tests](https://github.com/proxynth/larawebhook/actions/workflows/tests.yml/badge.svg)](https://github.com/proxynth/larawebhook/actions)
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

1. Create a route for webhooks
   ```php
   // routes/web.php
   use Proxynth\LaraWebhook\Http\Controllers\WebhookController;

   Route::post('/stripe-webhook', [WebhookController::class, 'handleWebhook']);
   ```

2. Validate and process a webhook
    ```php
    // app/Http/Controllers/WebhookController.php
    use Proxynth\LaraWebhook\Facades\LaraWebhook;
    use Illuminate\Http\Request;

    public function handleWebhook(Request $request)
    {
        $webhook = LaraWebhook::constructEvent(
            $request->getContent(),
            $request->header('Stripe-Signature'),
            config('larawebhook.stripe.secret')
        );

        if ($webhook->isValid()) {
            // Process the event (e.g., payment_intent.succeeded)
            event(new \App\Events\StripeWebhookReceived($webhook));
            return response()->json(['status' => 'success']);
        }

        abort(403, 'Invalid signature');
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
