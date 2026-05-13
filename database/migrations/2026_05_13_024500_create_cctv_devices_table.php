<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cctv_devices', function (Blueprint $table) {
            $table->id();
            $table->string('branch');
            $table->string('office');
            $table->string('dvr_brand');
            $table->unsignedInteger('channel_count');
            $table->string('harddisk');
            $table->string('monitor');
            $table->enum('connection_status', ['online', 'offline'])->default('online');
            $table->enum('device_status', ['normal', 'perlu_perbaikan'])->default('normal');
            $table->text('notes')->nullable();
            $table->string('dvr_photo_path')->nullable();
            $table->string('monitor_photo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cctv_devices');
    }
};
