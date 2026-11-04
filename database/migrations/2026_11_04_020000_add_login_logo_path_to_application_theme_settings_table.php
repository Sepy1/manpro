<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_theme_settings', function (Blueprint $table) {
            $table->string('login_logo_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('application_theme_settings', function (Blueprint $table) {
            $table->dropColumn('login_logo_path');
        });
    }
};
