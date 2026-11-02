<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_theme_settings', function (Blueprint $table) {
            $table->string('font_family', 30)->default('figtree')->after('theme');
            $table->string('font_size', 30)->default('normal')->after('font_family');
        });
    }

    public function down(): void
    {
        Schema::table('application_theme_settings', function (Blueprint $table) {
            $table->dropColumn(['font_family', 'font_size']);
        });
    }
};
