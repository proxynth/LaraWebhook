<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Proxynth\Larawebhook\Database\Factories\WebhookLogFactory;

/**
 * @property string $service
 * @property string $event
 * @property string $status
 * @property array $payload
 * @property string|null $error_message
 * @property int $attempt
 */
class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
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
