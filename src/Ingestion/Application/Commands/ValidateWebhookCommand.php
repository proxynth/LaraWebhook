<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Commands;

use Proxynth\Larawebhook\Enums\WebhookService;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Application\Commands\Command;

final readonly class ValidateWebhookCommand implements Command
{
    public function __construct(
        public WebhookService $service,
        public RawPayload $payload,
        public Signature $signature,
        public string $event,
        public ?string $externalId,
        public string $secret,
    ) {}
}
