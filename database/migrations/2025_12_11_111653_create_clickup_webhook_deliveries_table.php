<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clickup_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clickup_webhook_id')->constrained()->cascadeOnDelete();

            $table->string('event');
            $table->json('payload');
            $table->string('status'); // received, processed, failed
            $table->text('error_message')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->string('idempotency_key')->unique();

            $table->timestamps();

            $table->index('created_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clickup_webhook_deliveries');
    }
};
