<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Services;

use Proxynth\Larawebhook\Enums\PayloadStorageMode;

readonly class PayloadStorageResolver
{
    public function __construct(
        private PayloadRedactor $payloadRedactor,
    ) {}

    public function resolve(array $payload): ?array
    {
        $mode = PayloadStorageMode::fromConfig(
            config('larawebhook.payload_storage.mode', 'redacted')
        );

        return match ($mode) {
            PayloadStorageMode::None => null,
            PayloadStorageMode::Redacted => $this->payloadRedactor->redact($payload),
            PayloadStorageMode::Full => $payload,
        };
    }
}
