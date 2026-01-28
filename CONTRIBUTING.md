# Contributing to LaraWebhook

Thank you for your interest in contributing to LaraWebhook! This document provides guidelines and information for contributors.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for everyone.

## How to Contribute

### Reporting Bugs

Before creating a bug report, please check existing issues to avoid duplicates.

When reporting a bug, include:

- **Clear title** describing the issue
- **Steps to reproduce** the behavior
- **Expected behavior** vs actual behavior
- **Environment details** (PHP version, Laravel version, package version)
- **Code samples** or error messages if applicable

### Suggesting Features

Feature requests are welcome! Please:

- **Check existing issues** for similar suggestions
- **Describe the use case** - what problem does it solve?
- **Provide examples** of how the feature would work
- **Consider backward compatibility**

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Follow the coding standards** (see below)
3. **Write tests** for new functionality
4. **Update documentation** if needed
5. **Ensure all tests pass**
6. **Submit your pull request**

## Development Setup

### Prerequisites

- PHP 8.3+
- Composer

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/larawebhook.git
cd larawebhook

# Install dependencies
composer install
```

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
./vendor/bin/pest tests/Unit/YourTest.php
```

### Code Quality

```bash
# Run static analysis
composer analyse

# Format code
composer format
```

## Coding Standards

### PHP Style

- Follow PSR-12 coding standards
- Use Laravel Pint for formatting (`composer format`)
- Use strict types: `declare(strict_types=1);`
- Use meaningful variable and method names

### Architecture

- Follow SOLID principles
- Use the Strategy Pattern for new service implementations
- Keep classes focused and single-purpose
- Prefer composition over inheritance

### Adding a New Webhook Service

1. Create a `PayloadParser` in `src/Parsers/`:

```php
<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Parsers;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;

class YourServicePayloadParser implements PayloadParserInterface
{
    public function extractEventType(array $data): string
    {
        // Implementation
    }

    public function extractMetadata(array $data): array
    {
        // Implementation
    }

    public function serviceName(): string
    {
        return 'your-service';
    }
}
```

2. Create a `SignatureValidator` in `src/Validators/`:

```php
<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Validators;

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;

class YourServiceSignatureValidator implements SignatureValidatorInterface
{
    public function validate(string $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        // Implementation
    }

    public function serviceName(): string
    {
        return 'your-service';
    }
}
```

3. Register in `src/Enums/WebhookService.php`

4. Add configuration in `config/larawebhook.php`

5. Write comprehensive tests

### Testing Guidelines

- Write tests for all new functionality
- Use descriptive test names
- Test both success and failure cases
- Test edge cases and error conditions
- Maintain high code coverage

### Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): description

[optional body]

[optional footer]
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

Examples:
```
feat(parser): add Twilio payload parser
fix(validator): handle empty signature gracefully
docs(readme): add Slack integration example
test(middleware): add tests for edge cases
```

## Questions?

If you have questions about contributing, feel free to:

- Open a GitHub Discussion
- Create an issue with the `question` label

Thank you for contributing to LaraWebhook! 🎉
