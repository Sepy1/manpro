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
            $table->unsignedSmallInteger('live_refresh_seconds')
                ->default(LivestreamSetting::DEFAULT_LIVE_REFRESH_SECONDS)
                ->after('swipe_interval_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('livestream_settings', function (Blueprint $table) {
            $table->dropColumn('live_refresh_seconds');
        });
    }
};
