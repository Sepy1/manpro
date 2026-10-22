<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('hostname');
            $table->string('machine_guid')->unique();
            $table->string('serial_number');
            $table->string('mainboard_type');
            $table->string('antivirus_status', 50);
            $table->string('antivirus_name', 500);
            $table->string('os_info');
            $table->string('cpu_name');
            $table->unsignedSmallInteger('cpu_cores');
            $table->unsignedSmallInteger('cpu_threads');
            $table->decimal('ram_gb', 12, 1);
            $table->decimal('disk_total_gb', 15, 1);
            $table->decimal('disk_free_gb', 15, 1);
            $table->text('disk_detail');
            $table->string('mac_address', 50);
            $table->string('ip_address', 50);
            $table->string('kode_kantor', 3)->index();
            $table->string('nama_user');
            $table->string('jabatan');
            $table->timestamp('last_seen_at')->index();
            $table->string('sender_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_inventories');
    }
};
