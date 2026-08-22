<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Commands;

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\RawPayload;
use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Application\Commands\Command;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

final readonly class ValidateWebhookCommand implements Command
{
    public function __construct(
        public WebhookServiceIdentifier $service,
        public RawPayload $payload,
        public Signature $signature,
        public string $event,
        public ?string $externalId,
        public string $secret,
    ) {}
}
