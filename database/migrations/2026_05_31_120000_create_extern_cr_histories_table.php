<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('extern_cr_histories')) {
            return;
        }

        Schema::create('extern_cr_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extern_cr_id')->constrained('extern_crs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->string('summary')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['extern_cr_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extern_cr_histories');
    }
};
