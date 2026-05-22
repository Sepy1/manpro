<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extern_cr_applications')) {
            Schema::create('extern_cr_applications', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('extern_cr_change_reasons')) {
            Schema::create('extern_cr_change_reasons', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('extern_cr_change_reasons');
        Schema::dropIfExists('extern_cr_applications');
    }
};
