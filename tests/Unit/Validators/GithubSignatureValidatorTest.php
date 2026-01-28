<?php

declare(strict_types=1);

use Proxynth\Larawebhook\Contracts\SignatureValidatorInterface;
use Proxynth\Larawebhook\Exceptions\InvalidSignatureException;
use Proxynth\Larawebhook\Validators\GithubSignatureValidator;

describe('GithubSignatureValidator', function () {
    beforeEach(function () {
        $this->validator = new GithubSignatureValidator;
        $this->secret = 'test_github_secret';
    });

    it('implements SignatureValidatorInterface', function () {
        expect($this->validator)->toBeInstanceOf(SignatureValidatorInterface::class);
    });

    it('returns github as service name', function () {
        expect($this->validator->serviceName())->toBe('github');
    });
});

describe('GithubSignatureValidator validate', function () {
    beforeEach(function () {
        $this->validator = new GithubSignatureValidator;
        $this->secret = 'test_github_secret';
    });

    it('validates correct signature', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $this->secret);

        $result = $this->validator->validate($payload, $signature, $this->secret);

        expect($result)->toBeTrue();
    });

    it('throws exception for missing sha256 prefix', function () {
        $payload = '{"action": "opened"}';
        $signature = hash_hmac('sha256', $payload, $this->secret); // Missing 'sha256=' prefix

        expect(fn () => $this->validator->validate($payload, $signature, $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid GitHub signature format.');
    });

    it('throws exception for wrong prefix', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha1='.hash_hmac('sha256', $payload, $this->secret);

        expect(fn () => $this->validator->validate($payload, $signature, $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid GitHub signature format.');
    });

    it('throws exception for invalid signature', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256=invalid_hash_value';

        expect(fn () => $this->validator->validate($payload, $signature, $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid GitHub webhook signature.');
    });

    it('throws exception for empty signature', function () {
        $payload = '{"action": "opened"}';

        expect(fn () => $this->validator->validate($payload, '', $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid GitHub signature format.');
    });

    it('throws exception for signature with wrong secret', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'wrong_secret');

        expect(fn () => $this->validator->validate($payload, $signature, $this->secret))
            ->toThrow(InvalidSignatureException::class, 'Invalid GitHub webhook signature.');
    });

    it('ignores tolerance parameter (GitHub does not use timestamps)', function () {
        $payload = '{"action": "opened"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $this->secret);

        // Tolerance should have no effect on GitHub validation
        $result = $this->validator->validate($payload, $signature, $this->secret, 0);

        expect($result)->toBeTrue();
    });

    it('validates with complex JSON payload', function () {
        $payload = '{"action":"synchronize","pull_request":{"id":123,"title":"Test PR"},"sender":{"login":"octocat"}}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, $this->secret);

        $result = $this->validator->validate($payload, $signature, $this->secret);

        expect($result)->toBeTrue();
    });
});
