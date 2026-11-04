<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attack_map_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('headquarters_kantor_id')->nullable()->constrained('kantors')->nullOnDelete();
            $table->json('central_ip_ranges')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attack_map_settings');
    }
};
