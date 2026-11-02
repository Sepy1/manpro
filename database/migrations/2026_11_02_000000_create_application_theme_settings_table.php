<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_theme_settings', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 30)->default('midnight');
            $table->timestamps();
        });

        DB::table('application_theme_settings')->insert([
            'theme' => 'midnight',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('application_theme_settings');
    }
};
