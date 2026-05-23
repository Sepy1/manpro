<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            $table->string('wa_authorization_decision', 20)->nullable();
            $table->timestamp('wa_authorization_at')->nullable();
            $table->foreignId('wa_authorization_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('extern_crs', function (Blueprint $table) {
            $table->dropForeign(['wa_authorization_by_user_id']);
            $table->dropColumn([
                'wa_authorization_decision',
                'wa_authorization_at',
                'wa_authorization_by_user_id',
            ]);
        });
    }
};
