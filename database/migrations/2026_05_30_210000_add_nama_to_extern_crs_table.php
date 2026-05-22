<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            if (! Schema::hasColumn('extern_crs', 'nama')) {
                $table->string('nama')->nullable()->after('bidang');
            }
        });
    }

    public function down(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            if (Schema::hasColumn('extern_crs', 'nama')) {
                $table->dropColumn('nama');
            }
        });
    }
};
