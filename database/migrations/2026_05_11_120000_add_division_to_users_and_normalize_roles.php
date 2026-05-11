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
            if (!Schema::hasColumn('users', 'division')) {
                $table->string('division')->nullable()->after('role');
            }
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            // Allow transition values first, so updates below do not fail.
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','office','vendor','user','officer') NOT NULL DEFAULT 'user'");
        }

        DB::table('users')->where('role', 'office')->update(['role' => 'officer']);
        DB::table('users')->where('role', 'user')->update(['role' => 'officer']);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            // Keep vendor role available as requested.
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','officer','vendor') NOT NULL DEFAULT 'officer'");
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'officer')->update(['role' => 'office']);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','office','vendor','user') NOT NULL DEFAULT 'user'");
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'division')) {
                $table->dropColumn('division');
            }
        });
    }
};
