<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotIn('role', ['admin', 'manager', 'officer', 'vendor', 'cabang', 'vendor_network'])
            ->update(['role' => 'officer']);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','officer','vendor','cabang','vendor_network') NOT NULL DEFAULT 'officer'");
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'vendor_network')->update(['role' => 'officer']);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','officer','vendor','cabang') NOT NULL DEFAULT 'officer'");
        }
    }
};
