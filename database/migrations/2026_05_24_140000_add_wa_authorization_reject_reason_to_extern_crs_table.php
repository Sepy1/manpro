<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            $table->text('wa_authorization_reject_reason')->nullable()->after('wa_authorization_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            $table->dropColumn('wa_authorization_reject_reason');
        });
    }
};
