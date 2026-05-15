<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();

            $table->string('attempted_email')->nullable()->after('user_id');
            $table->string('failure_reason')->nullable()->after('activity_type');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['activity_type', 'attempted_email']);
        });
    }

    public function down(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropIndex(['activity_type', 'attempted_email']);
            $table->dropColumn(['attempted_email', 'failure_reason']);

            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
