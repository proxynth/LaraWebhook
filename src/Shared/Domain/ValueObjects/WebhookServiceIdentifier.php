<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Shared\Domain\ValueObjects;

interface WebhookServiceIdentifier
{
    public function value(): string;

    public static function fromString(string $value): self;
}
