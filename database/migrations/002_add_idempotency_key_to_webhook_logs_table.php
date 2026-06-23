<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('webhook_logs', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('external_id');
                $table->unique(['service', 'idempotency_key'], 'webhook_logs_service_idempotency_key_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('webhook_logs', 'idempotency_key')) {
                $table->dropUnique('webhook_logs_service_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
