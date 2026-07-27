<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('extern_cr_api_keys')) {
            return;
        }

        Schema::create('extern_cr_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 12)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extern_cr_api_keys');
    }
};
