<?php

use Proxynth\Larawebhook\Ingestion\Application\Data\IncomingWebhookSignature;

function incomingSignature(string $value, ?string $timestamp = null): IncomingWebhookSignature
{
    return new IncomingWebhookSignature(
        value: $value,
        timestamp: $timestamp,
    );
}

function slackIncomingSignature(string $value, int|string $timestamp): IncomingWebhookSignature
{
    return new IncomingWebhookSignature(
        value: $value,
        timestamp: (string) $timestamp,
    );
}
