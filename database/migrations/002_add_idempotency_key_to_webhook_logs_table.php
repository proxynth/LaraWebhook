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
        if (! Schema::hasColumn('webhook_logs', 'idempotency_key')) {
            Schema::table('webhook_logs', function (Blueprint $table): void {
                $table->string('idempotency_key')->nullable()->after('external_id');
            });
        }

        $this->backfillIdempotencyKeys();

        Schema::table('webhook_logs', function (Blueprint $table): void {
            $table->unique(['service', 'idempotency_key'], 'webhook_logs_service_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('webhook_logs', 'idempotency_key')) {
            Schema::table('webhook_logs', function (Blueprint $table): void {
                $table->dropUnique('webhook_logs_service_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            });
        }
    }

    /**
     * Backfill historical logs where external_id previously acted as the deduplication key.
     *
     * Only one row per service + external_id pair receives an idempotency_key.
     * Historical duplicates remain audit-only rows with idempotency_key = null,
     * which prevents migration failures when adding the unique index.
     */
    private function backfillIdempotencyKeys(): void
    {
        DB::table('webhook_logs')
            ->select('service', 'external_id', DB::raw('MIN(id) as id'))
            ->whereNotNull('external_id')
            ->whereNull('idempotency_key')
            ->groupBy('service', 'external_id')
            ->orderBy('id')
            ->get()
            ->each(fn (object $row): int => DB::table('webhook_logs')
                ->where('id', $row->id)
                ->update(['idempotency_key' => $row->external_id])
            );
    }
};
