<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Ingestion\Application\Ports;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;
use Proxynth\Larawebhook\Shared\Domain\ValueObjects\WebhookServiceIdentifier;

interface WebhookPayloadParserResolver
{
    public function forService(WebhookServiceIdentifier $service): PayloadParserInterface;
}
