<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantors', function (Blueprint $table) {
            if (! Schema::hasColumn('kantors', 'kantor_kas')) {
                $table->string('kantor_kas')->nullable()->after('nama_kantor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kantors', function (Blueprint $table) {
            if (Schema::hasColumn('kantors', 'kantor_kas')) {
                $table->dropColumn('kantor_kas');
            }
        });
    }
};
