<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            $table->foreignId('vendor_pic_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            $table->dropForeign(['vendor_pic_user_id']);
            $table->dropColumn('vendor_pic_user_id');
        });
    }
};
