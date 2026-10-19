<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_monitor_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('high_ping_threshold_ms')->default(50)->after('polling_interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('network_monitor_settings', fn (Blueprint $table) => $table->dropColumn('high_ping_threshold_ms'));
    }
};
