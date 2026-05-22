<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extern_cr_divisions')) {
            Schema::create('extern_cr_divisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('extern_cr_id')->constrained('extern_crs')->cascadeOnDelete();
                $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['extern_cr_id', 'division_id']);
            });
        }

        if (! Schema::hasTable('extern_cr_attachments')) {
            Schema::create('extern_cr_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('extern_cr_id')->constrained('extern_crs')->cascadeOnDelete();
                $table->string('disk')->default('public');
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime', 127)->nullable();
                $table->unsignedInteger('size_bytes')->nullable();
                $table->unsignedTinyInteger('position')->default(0);
                $table->timestamps();

                $table->index(['extern_cr_id', 'position']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('extern_cr_attachments');
        Schema::dropIfExists('extern_cr_divisions');
    }
};
