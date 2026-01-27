<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service')->index(); // stripe, github, etc.
            $table->string('event')->index(); // payment_intent.succeeded, push, etc.
            $table->string('status')->index(); // success, failed
            $table->json('payload'); // Raw webhook content
            $table->text('error_message')->nullable(); // Error details if failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
