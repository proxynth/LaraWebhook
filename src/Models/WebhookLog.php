<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
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
}
