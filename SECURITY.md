# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take security issues seriously. We appreciate your efforts to responsibly disclose your findings.

### How to Report

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to: **proxynth.tech@gmail.com**

Include the following information in your report:

- Type of issue (e.g., signature bypass, injection, information disclosure)
- Full paths of source file(s) related to the issue
- Location of the affected source code (tag/branch/commit or direct URL)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

### What to Expect

- **Acknowledgment**: We will acknowledge receipt of your vulnerability report within 48 hours.
- **Communication**: We will keep you informed about our progress toward resolving the issue.
- **Timeline**: We aim to resolve critical vulnerabilities within 7 days.
- **Credit**: We will credit you in the release notes (unless you prefer to remain anonymous).

### Scope

The following are in scope for security reports:

- Signature validation bypass
- Authentication/authorization issues
- Injection vulnerabilities
- Information disclosure
- Cryptographic issues

### Out of Scope

- Issues in dependencies (please report to the respective project)
- Issues requiring physical access to the server
- Social engineering attacks
- Denial of service attacks

## Security Best Practices

When using LaraWebhook, please follow these security best practices:

1. **Always use HTTPS** in production for webhook endpoints
2. **Keep secrets secure** - never commit webhook secrets to version control
3. **Use environment variables** for all sensitive configuration
4. **Regularly rotate** webhook secrets
5. **Monitor logs** for suspicious webhook activity
6. **Keep the package updated** to receive security patches

## Security Updates

Security updates will be released as patch versions and announced in:

- GitHub Releases
- CHANGELOG.md
- GitHub Security Advisories (for critical issues)

Thank you for helping keep LaraWebhook and its users safe!
