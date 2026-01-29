# Changelog

All notable changes to `larawebhook` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.10.0](https://github.com/proxynth/LaraWebhook/compare/v1.9.1...v1.10.0) (2026-01-29)


### Features

* implement automatic idempotency for webhook handling ([#33](https://github.com/proxynth/LaraWebhook/issues/33)) ([e69a0dd](https://github.com/proxynth/LaraWebhook/commit/e69a0ddaa4ca1231d22d67784f208f84f414d8fa))

## [1.9.1](https://github.com/proxynth/LaraWebhook/compare/v1.9.0...v1.9.1) (2026-01-28)


### Bug Fixes

* **docs:** correct base URL case for GitHub Pages ([#31](https://github.com/proxynth/LaraWebhook/issues/31)) ([5a52d76](https://github.com/proxynth/LaraWebhook/commit/5a52d7603648b7f4285a4719538ae6f94414f411))

## [1.9.0](https://github.com/proxynth/LaraWebhook/compare/v1.8.0...v1.9.0) (2026-01-28)


### Features

* add documentation and configuration for new webhook services ([#29](https://github.com/proxynth/LaraWebhook/issues/29)) ([eb354de](https://github.com/proxynth/LaraWebhook/commit/eb354de616aa1c3503783e6dc0b3d58f1eaef7b7))

## [1.8.0](https://github.com/proxynth/LaraWebhook/compare/v1.7.0...v1.8.0) (2026-01-28)


### Features

* add support for Slack and Shopify webhooks ([#25](https://github.com/proxynth/LaraWebhook/issues/25)) ([50233dc](https://github.com/proxynth/LaraWebhook/commit/50233dcef8d0920ee6d866a062914cba61aef24b))

## [1.7.0](https://github.com/proxynth/LaraWebhook/compare/v1.6.0...v1.7.0) (2026-01-28)


### Features

* implement failure notification system for webhooks ([#23](https://github.com/proxynth/LaraWebhook/issues/23)) ([8321b3b](https://github.com/proxynth/LaraWebhook/commit/8321b3bb5ea583b8576cc1bbd2e9a1b95398b691))

## [1.6.0](https://github.com/proxynth/LaraWebhook/compare/v1.5.1...v1.6.0) (2026-01-28)


### Features

* enhance README and add example controllers for Stripe and GitHub webhooks ([#21](https://github.com/proxynth/LaraWebhook/issues/21)) ([fada5df](https://github.com/proxynth/LaraWebhook/commit/fada5df69d9b0a6197c374e38582d9c749521a7e))

## [1.5.1](https://github.com/proxynth/LaraWebhook/compare/v1.5.0...v1.5.1) (2026-01-27)


### Bug Fixes

* update Codecov badge in README for consistency with GitHub repository ([#17](https://github.com/proxynth/LaraWebhook/issues/17)) ([6f80e3e](https://github.com/proxynth/LaraWebhook/commit/6f80e3e7589b31b010fb426821c73c30ea8fac20))

## [1.5.0](https://github.com/proxynth/LaraWebhook/compare/v1.4.0...v1.5.0) (2026-01-27)


### Features

* :sparkles: enhance testing setup with code coverage reporting ([#14](https://github.com/proxynth/LaraWebhook/issues/14)) ([1cd9b49](https://github.com/proxynth/LaraWebhook/commit/1cd9b49a619f964e0a4af6dca177a3654907dc10))

## [1.4.0](https://github.com/proxynth/LaraWebhook/compare/v1.3.0...v1.4.0) (2026-01-27)


### Features

* :sparkles: introduce webhook validation middleware and enhance README ([#12](https://github.com/proxynth/LaraWebhook/issues/12)) ([74c3cfd](https://github.com/proxynth/LaraWebhook/commit/74c3cfde327d9efe5231871e7252c4dedf92acf3))

## [1.3.0](https://github.com/proxynth/LaraWebhook/compare/v1.2.0...v1.3.0) (2026-01-27)


### Features

* :sparkles: implement webhook retry mechanism with logging and configuration support ([#10](https://github.com/proxynth/LaraWebhook/issues/10)) ([478c64b](https://github.com/proxynth/LaraWebhook/commit/478c64b655563c61ad180aa85e7a8a90d23730a3))

## [1.2.0](https://github.com/proxynth/LaraWebhook/compare/v1.1.0...v1.2.0) (2026-01-27)


### Features

* :sparkles: add webhook logging functionality with database migration and model support ([#8](https://github.com/proxynth/LaraWebhook/issues/8)) ([b194b5c](https://github.com/proxynth/LaraWebhook/commit/b194b5c575c9701eebc3122b4af071f6048eaf7b))

## [1.1.0](https://github.com/proxynth/LaraWebhook/compare/v1.0.0...v1.1.0) (2026-01-27)


### Features

* :sparkles: implement webhook validation service with support fo… ([11c229f](https://github.com/proxynth/LaraWebhook/commit/11c229ff7629dbf0bb0d244f78f7e1a4c0b27aca))
* :sparkles: implement webhook validation service with support for Stripe and GitHub signatures, including exception handling ([3a28b18](https://github.com/proxynth/LaraWebhook/commit/3a28b18b9adb594686a427bb8cd014ca04967f3e))

## 1.0.0 (2026-01-26)


### Features

* :sparkles: add configuration for webhook services and update se… ([4bda43b](https://github.com/proxynth/LaraWebhook/commit/4bda43b3e66fc0530c6f60d3f0275b1b9bf6e8f4))
* :sparkles: add configuration for webhook services and update service provider ([7eb4e78](https://github.com/proxynth/LaraWebhook/commit/7eb4e7837dcdfdc1c801fe09eb27811d168f0bfe))
* :sparkles: add README, changelog, CI workflows, and PHPStan con… ([da35f79](https://github.com/proxynth/LaraWebhook/commit/da35f79309c72bd17619c51195ec553f11b460dd))
* :sparkles: add README, changelog, CI workflows, and PHPStan configuration ([643104e](https://github.com/proxynth/LaraWebhook/commit/643104e7de341c6a26f59163d31a9306f0841dc8))
* :sparkles: setup laravel package skeleton ([6e1f8e0](https://github.com/proxynth/LaraWebhook/commit/6e1f8e015b1c8b6b669565083e07b914706bcbbb))
* :sparkles: setup laravel package skeleton ([fdbf8c0](https://github.com/proxynth/LaraWebhook/commit/fdbf8c0dcd1d57aefa6defb92a4eecdd50bd2a3e))

## [Unreleased]

### Features

- Initial package setup with Laravel service provider
- Configuration file for webhook services (Stripe, GitHub)

### Continuous Integration

- Add PHPStan static analysis with baseline
- Add Pint code style checking
- Add Pest testing framework setup
- Add automated release workflow with Release Please
