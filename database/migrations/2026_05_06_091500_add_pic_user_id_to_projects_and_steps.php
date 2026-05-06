<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'pic_user_id')) {
                $table->foreignId('pic_user_id')->nullable()->after('pic')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('project_steps', function (Blueprint $table) {
            if (!Schema::hasColumn('project_steps', 'pic_user_id')) {
                $table->foreignId('pic_user_id')->nullable()->after('pic')->constrained('users')->nullOnDelete();
            }
        });

        // Backfill from legacy pic name when possible.
        DB::statement("
            UPDATE projects p
            JOIN users u ON u.name = p.pic
            SET p.pic_user_id = u.id
            WHERE p.pic IS NOT NULL AND p.pic <> '' AND p.pic_user_id IS NULL
        ");

        DB::statement("
            UPDATE project_steps s
            JOIN users u ON u.name = s.pic
            SET s.pic_user_id = u.id
            WHERE s.pic IS NOT NULL AND s.pic <> '' AND s.pic_user_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('project_steps', function (Blueprint $table) {
            if (Schema::hasColumn('project_steps', 'pic_user_id')) {
                $table->dropConstrainedForeignId('pic_user_id');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'pic_user_id')) {
                $table->dropConstrainedForeignId('pic_user_id');
            }
        });
    }
};

