<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    private const INDEX_NAME = 'webhook_logs_service_idempotency_key_unique';

    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if ($this->hasDuplicateIdempotencyKeys()) {
            throw new RuntimeException('Cannot restore webhook_logs unique index because duplicate service + idempotency_key rows exists. '
            .'processed_webhook_events is the deduplication source of truth.');
        }

        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->unique(['service', 'idempotency_key'], self::INDEX_NAME);
        });
    }

    private function hasDuplicateIdempotencyKeys(): bool
    {
        return DB::table('webhook_logs')
            ->select('service', 'idempotency_key')
            ->whereNotNull('idempotency_key')
            ->groupBy('service', 'idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }
};
