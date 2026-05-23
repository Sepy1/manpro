<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_cr_authorization_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extern_cr_id')->constrained('extern_crs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            /** @example wamid.HBgLNjI4Mz... */
            $table->string('wam_id')->nullable();
            $table->string('recipient_wa_id', 32);
            $table->timestamps();

            $table->index('wam_id', 'wa_disp_wam_idx');
            $table->index(['extern_cr_id', 'user_id'], 'wa_disp_cr_user_idx');
            $table->index(['recipient_wa_id', 'created_at'], 'wa_disp_rcpt_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_cr_authorization_dispatches');
    }
};
