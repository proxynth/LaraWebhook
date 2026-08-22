<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Commands;

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;
use Proxynth\Larawebhook\Shared\Application\Commands\Command;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

final readonly class ReceiveWebhookCommand implements Command
{
    public function __construct(
        public WebhookServiceIdentifier $service,
        public string $payload,
        public Signature $signature,
        public ?string $externalIdHeaderValue = null,
    ) {}
}
