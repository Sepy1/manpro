<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('vendor', 100);
            $table->string('device_name');
            $table->string('device_id', 100)->index();
            $table->string('virtual_domain', 100)->nullable();
            $table->string('event_type', 100)->index();
            $table->string('event_category', 100)->index();
            $table->string('username', 255)->index();
            $table->string('auth_server')->nullable();
            $table->string('source_ip', 45)->index();
            $table->string('destination_ip', 45)->nullable();
            $table->string('tunnel_ip', 45)->nullable();
            $table->string('source_interface')->nullable();
            $table->string('destination_interface')->nullable();
            $table->string('method', 50)->nullable();
            $table->string('action', 100)->index();
            $table->string('status', 50)->index();
            $table->string('original_status', 100)->nullable();
            $table->string('reason')->nullable();
            $table->string('profile')->nullable();
            $table->date('event_date')->index();
            $table->time('event_clock');
            $table->string('timezone', 10)->nullable();
            $table->string('event_time', 100)->index();
            $table->string('log_id', 100)->index();
            $table->text('log_description')->nullable();
            $table->string('severity', 50)->index();
            $table->text('message')->nullable();
            $table->longText('raw_log');
            $table->timestamps();

            $table->index(['event_date', 'event_clock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_access_logs');
    }
};
