<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('service');
            $table->string('idempotency_key');
            $table->string('external_id')->nullable();
            $table->string('event')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(
                ['service', 'idempotency_key'],
                'processed_webhook_events_service_idempotency_key_unique'
            );

            $table->index(['service', 'external_id']);
            $table->index(['service', 'event']);
        });

        $this->backfillProcessedWebhookEvents();
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_webhook_events');
    }

    /**
     * Backfill processed webhook events from historical successful audit logs.
     *
     * Only successful logs with an idempotency_key are considered processed.
     * webhook_logs remains an audit trail and is not used for deduplication after this migration.
     */
    private function backfillProcessedWebhookEvents(): void
    {
        DB::table('webhook_logs')
            ->select('service', 'idempotency_key', DB::raw('MIN(id) as id'))
            ->where('status', 'success')
            ->whereNotNull('idempotency_key')
            ->groupBy('service', 'idempotency_key')
            ->get()
            ->each(function (object $row): void {
                $log = DB::table('webhook_logs')
                    ->where('id', $row->id)
                    ->first();

                if ($log === null) {
                    return;
                }

                DB::table('processed_webhook_events')->insertOrIgnore([
                    'service' => $log->service,
                    'idempotency_key' => $log->idempotency_key,
                    'external_id' => $log->external_id,
                    'event' => $log->event,
                    'processed_at' => $log->created_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
};
