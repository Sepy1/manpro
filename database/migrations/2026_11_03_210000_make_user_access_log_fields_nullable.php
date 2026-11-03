<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_access_logs', function (Blueprint $table) {
            $table->string('vendor', 100)->nullable()->change();
            $table->string('device_name')->nullable()->change();
            $table->string('device_id', 100)->nullable()->change();
            $table->string('event_type', 100)->nullable()->change();
            $table->string('event_category', 100)->nullable()->change();
            $table->string('username')->nullable()->change();
            $table->string('source_ip', 45)->nullable()->change();
            $table->string('action', 100)->nullable()->change();
            $table->string('status', 50)->nullable()->change();
            $table->date('event_date')->nullable()->change();
            $table->time('event_clock')->nullable()->change();
            $table->string('event_time', 100)->nullable()->change();
            $table->string('log_id', 100)->nullable()->change();
            $table->string('severity', 50)->nullable()->change();
            $table->longText('raw_log')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_access_logs', function (Blueprint $table) {
            $table->string('vendor', 100)->nullable(false)->change();
            $table->string('device_name')->nullable(false)->change();
            $table->string('device_id', 100)->nullable(false)->change();
            $table->string('event_type', 100)->nullable(false)->change();
            $table->string('event_category', 100)->nullable(false)->change();
            $table->string('username')->nullable(false)->change();
            $table->string('source_ip', 45)->nullable(false)->change();
            $table->string('action', 100)->nullable(false)->change();
            $table->string('status', 50)->nullable(false)->change();
            $table->date('event_date')->nullable(false)->change();
            $table->time('event_clock')->nullable(false)->change();
            $table->string('event_time', 100)->nullable(false)->change();
            $table->string('log_id', 100)->nullable(false)->change();
            $table->string('severity', 50)->nullable(false)->change();
            $table->longText('raw_log')->nullable(false)->change();
        });
    }
};
