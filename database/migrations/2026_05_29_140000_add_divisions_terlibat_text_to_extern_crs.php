<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            if (! Schema::hasColumn('extern_crs', 'divisions_terlibat_text')) {
                $table->text('divisions_terlibat_text')->nullable()->after('prioritas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            if (Schema::hasColumn('extern_crs', 'divisions_terlibat_text')) {
                $table->dropColumn('divisions_terlibat_text');
            }
        });
    }
};
