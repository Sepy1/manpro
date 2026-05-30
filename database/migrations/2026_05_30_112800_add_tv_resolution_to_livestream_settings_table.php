<?php

use App\Models\LivestreamSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livestream_settings', function (Blueprint $table) {
            $table->string('tv_resolution', 32)
                ->default(LivestreamSetting::DEFAULT_TV_RESOLUTION)
                ->after('live_refresh_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('livestream_settings', function (Blueprint $table) {
            $table->dropColumn('tv_resolution');
        });
    }
};
