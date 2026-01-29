<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Proxynth\Larawebhook\Database\Factories\WebhookLogFactory;

/**
 * @property string $service
 * @property string|null $external_id
 * @property string $event
 * @property string $status
 * @property array $payload
 * @property string|null $error_message
 * @property int $attempt
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'external_id',
        'event',
        'status',
        'payload',
        'error_message',
        'attempt',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempt' => 'integer',
    ];

    /**
     * Scope to filter by service.
     */
    public function scopeService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Scope to filter by external ID.
     */
    public function scopeExternalId($query, string $externalId)
    {
        return $query->where('external_id', $externalId);
    }

    /**
     * Check if a webhook with this external ID already exists for the given service.
     */
    public static function existsForExternalId(string $service, string $externalId): bool
    {
        return static::where('service', $service)
            ->where('external_id', $externalId)
            ->exists();
    }

    /**
     * Find a webhook log by service and external ID.
     *
     * @return static|null
     */
    public static function findByExternalId(string $service, string $externalId): ?self
    {
        /** @var static|null */
        return static::where('service', $service)
            ->where('external_id', $externalId)
            ->first();
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by event.
     */
    public function scopeEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope to get failed webhooks.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get successful webhooks.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to filter by attempt number.
     */
    public function scopeAttempt($query, int $attempt)
    {
        return $query->where('attempt', $attempt);
    }

    /**
     * Scope to get retried webhooks (attempt > 0).
     */
    public function scopeRetried($query)
    {
        return $query->where('attempt', '>', 0);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): WebhookLogFactory
    {
        return WebhookLogFactory::new();
    }
}
