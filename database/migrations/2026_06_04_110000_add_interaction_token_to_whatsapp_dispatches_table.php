<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_cr_authorization_dispatches', function (Blueprint $table) {
            $table->string('interaction_token', 48)->nullable()->unique('wa_disp_itok_uq');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_cr_authorization_dispatches', function (Blueprint $table) {
            $table->dropUnique('wa_disp_itok_uq');
            $table->dropColumn('interaction_token');
        });
    }
};
