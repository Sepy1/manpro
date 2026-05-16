<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dc_drc_devices', function (Blueprint $table) {
            $table->id();
            $table->string('server_name');
            $table->string('device_type')->nullable();
            $table->string('host_server')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('vlan')->nullable();
            $table->string('nic_model')->nullable();
            $table->string('os')->nullable();
            $table->unsignedInteger('cpu_cores')->nullable();
            $table->unsignedInteger('ram_gb')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->string('site')->nullable();
            $table->string('system_role')->nullable();
            $table->string('environment')->nullable();
            $table->string('owner_team')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['server_name']);
            $table->index(['ip_address']);
            $table->index(['site']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_drc_devices');
    }
};
