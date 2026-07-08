<?php

declare(strict_types=1);

namespace Proxynth\Larawebhook\Processing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ProcessedWebhookEvent extends Model
{
    protected $table = 'processed_webhook_events';

    protected $fillable = [
        'service',
        'idempotency_key',
        'external_id',
        'event',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
