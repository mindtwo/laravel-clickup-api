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
            $table->integer('deliveries_since_recovery')
                ->default(0)
                ->after('total_deliveries');
            $table->integer('failed_deliveries_since_recovery')
                ->default(0)
                ->after('failed_deliveries');
            $table->integer('recovery_count')
                ->default(0)
                ->after('failed_deliveries_since_recovery');
            $table->timestamp('recovered_at')
                ->nullable()
                ->after('recovery_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clickup_webhooks', function (Blueprint $table) {
            $table->dropColumn([
                'deliveries_since_recovery',
                'failed_deliveries_since_recovery',
                'recovery_count',
                'recovered_at',
            ]);
        });
    }
};
