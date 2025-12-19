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
        Schema::create('clickup_webhooks', function (Blueprint $table) {
            $table->id();

            // ClickUp webhook details
            $table->string('clickup_webhook_id')->nullable()->unique();
            $table->string('endpoint');
            $table->string('event');
            $table->string('health_status')->default('active');

            // Webhook targeting (ClickUp's hierarchy filtering)
            $table->string('target_type');
            $table->string('target_id');

            // Security & delivery tracking
            $table->string('secret')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('total_deliveries')->default(0);
            $table->integer('failed_deliveries')->default(0);
            $table->json('last_error')->nullable();

            // Metadata
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('event');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clickup_webhooks');
    }
};
