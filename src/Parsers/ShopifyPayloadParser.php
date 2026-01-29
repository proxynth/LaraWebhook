<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Parsers;

use Proxynth\Larawebhook\Contracts\PayloadParserInterface;

/**
 * Parser for Shopify webhook payloads.
 *
 * Shopify sends webhooks for various events:
 * - orders/create, orders/updated, orders/cancelled
 * - products/create, products/update, products/delete
 * - customers/create, customers/update
 * - etc.
 *
 * The event type is sent via X-Shopify-Topic header, not in the payload.
 *
 * @see https://shopify.dev/docs/api/webhooks
 */
class ShopifyPayloadParser implements PayloadParserInterface
{
    /**
     * Extract the event type from a Shopify webhook payload.
     *
     * Note: Shopify sends the topic via X-Shopify-Topic header.
     * If not available in payload, returns 'unknown'.
     *
     * @param  array<string, mixed>  $data
     */
    public function extractEventType(array $data): string
    {
        // Shopify doesn't include topic in payload, it's in headers
        // We might have it injected into the payload for logging purposes
        return $data['_topic'] ?? $data['topic'] ?? 'unknown';
    }

    /**
     * Extract metadata from a Shopify webhook payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extractMetadata(array $data): array
    {
        return [
            'id' => $data['id'] ?? null,
            'admin_graphql_api_id' => $data['admin_graphql_api_id'] ?? null,
            'shop_domain' => $data['_shop_domain'] ?? null,
            'order_number' => $data['order_number'] ?? null,
            'customer_id' => $data['customer']['id'] ?? null,
            'total_price' => $data['total_price'] ?? null,
            'currency' => $data['currency'] ?? null,
            'financial_status' => $data['financial_status'] ?? null,
        ];
    }

    /**
     * Extract the external ID from Shopify webhook.
     *
     * Shopify provides a unique webhook ID via X-Shopify-Webhook-Id header.
     *
     * @param  array<string, mixed>  $data
     */
    public function extractExternalId(array $data, ?string $headerValue = null): ?string
    {
        // Shopify sends the webhook ID via header
        return $headerValue ?? $data['_webhook_id'] ?? null;
    }

    /**
     * Get the service name this parser handles.
     */
    public function serviceName(): string
    {
        return 'shopify';
    }
}
