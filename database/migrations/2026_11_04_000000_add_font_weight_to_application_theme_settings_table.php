<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_theme_settings', function (Blueprint $table) {
            $table->string('font_weight', 20)->default('normal')->after('font_size');
        });
    }

    public function down(): void
    {
        Schema::table('application_theme_settings', function (Blueprint $table) {
            $table->dropColumn('font_weight');
        });
    }
};
