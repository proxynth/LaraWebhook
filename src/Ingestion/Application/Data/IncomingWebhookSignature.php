<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Data;

class IncomingWebhookSignature
{
    public function __construct(
        public string $value,
        public ?string $timestamp = null,
    ) {}
}
