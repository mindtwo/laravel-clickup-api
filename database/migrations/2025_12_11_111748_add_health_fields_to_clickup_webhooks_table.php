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
        Schema::table('clickup_webhooks', function (Blueprint $table) {
            $table->integer('fail_count')
                ->default(0)
                ->after('health_status');
            $table->timestamp('health_checked_at')
                ->nullable()
                ->after('fail_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clickup_webhooks', function (Blueprint $table) {
            $table->dropColumn(['fail_count', 'health_checked_at']);
        });
    }
};
