<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_monitoring_events', function (Blueprint $table) {
            $table->id();
            $table->dateTime('tanggal')->index();
            $table->string('nama_db');
            $table->decimal('ukuran', 15, 2);
            $table->string('status', 50)->index();
            $table->text('lokasi_file');
            $table->string('server')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_monitoring_events');
    }
};
