<?php

use App\Models\LivestreamSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livestream_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('swipe_interval_seconds')->default(LivestreamSetting::DEFAULT_INTERVAL_SECONDS);
            $table->json('selected_pages')->nullable();
            $table->timestamps();
        });

        DB::table('livestream_settings')->insert([
            'swipe_interval_seconds' => LivestreamSetting::DEFAULT_INTERVAL_SECONDS,
            'selected_pages' => json_encode(LivestreamSetting::defaultSelectedPages(), JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('livestream_settings');
    }
};
