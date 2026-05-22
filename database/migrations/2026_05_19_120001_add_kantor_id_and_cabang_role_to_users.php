<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'kantor_id')) {
                $table->foreignId('kantor_id')
                    ->nullable()
                    ->after('division')
                    ->constrained('kantors')
                    ->nullOnDelete();
            }
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','officer','vendor','cabang') NOT NULL DEFAULT 'officer'");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'kantor_id')) {
                $table->dropForeign(['kantor_id']);
                $table->dropColumn('kantor_id');
            }
        });

        DB::table('users')->where('role', 'cabang')->update(['role' => 'officer']);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','officer','vendor') NOT NULL DEFAULT 'officer'");
        }
    }
};
