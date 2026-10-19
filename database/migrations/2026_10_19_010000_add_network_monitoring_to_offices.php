<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['kantors', 'kas_kantor'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('wireless_ip', 45)->nullable()->after('longitude');
                $table->string('ping_status', 20)->nullable()->after('wireless_ip');
                $table->unsignedInteger('ping_latency_ms')->nullable()->after('ping_status');
                $table->timestamp('ping_checked_at')->nullable()->after('ping_latency_ms');
            });
        }

        Schema::create('network_monitor_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('polling_interval_minutes')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_monitor_settings');

        foreach (['kas_kantor', 'kantors'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn([
                'wireless_ip', 'ping_status', 'ping_latency_ms', 'ping_checked_at',
            ]));
        }
    }
};
