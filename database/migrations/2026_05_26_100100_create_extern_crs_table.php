<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('extern_crs')) {
            return;
        }

        Schema::create('extern_crs', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->unique();
            $table->date('tanggal');
            $table->unsignedSmallInteger('daily_sequence');
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->string('bidang', 255)->nullable();
            $table->foreignId('extern_cr_application_id')->constrained('extern_cr_applications')->restrictOnDelete();
            $table->string('jenis_perubahan', 20);
            $table->foreignId('extern_cr_change_reason_id')->constrained('extern_cr_change_reasons')->restrictOnDelete();
            $table->text('kondisi_saat_ini')->nullable();
            $table->text('perubahan_diharapkan')->nullable();
            $table->text('risiko_bila_tidak')->nullable();
            $table->string('prioritas', 20);
            $table->text('deskripsi_permintaan')->nullable();
            $table->timestamps();

            $table->unique(['tanggal', 'daily_sequence']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extern_crs');
    }
};
