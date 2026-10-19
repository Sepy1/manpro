<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantors', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('nama_kantor');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('kas_kantor', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('nama_kas');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('kas_kantor', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude']));
        Schema::table('kantors', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude']));
    }
};
