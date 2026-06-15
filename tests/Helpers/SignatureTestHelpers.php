<?php

use Proxynth\Larawebhook\Ingestion\Domain\ValueObjects\Signature;

function incomingSignature(string $value, ?string $timestamp = null): Signature
{
    return Signature::fromString(
        value: $value,
        timestamp: $timestamp,
    );
}

function slackIncomingSignature(string $value, int|string $timestamp): Signature
{
    return Signature::fromString(
        value: $value,
        timestamp: (string) $timestamp,
    );
}
