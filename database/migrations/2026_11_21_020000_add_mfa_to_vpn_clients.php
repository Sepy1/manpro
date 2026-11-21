<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_clients', function (Blueprint $table) {
            $table->timestamp('mfa_enabled_at')->nullable()->after('last_connected_at');
            $table->timestamp('mfa_last_reset_at')->nullable()->after('mfa_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('vpn_clients', function (Blueprint $table) {
            $table->dropColumn(['mfa_enabled_at', 'mfa_last_reset_at']);
        });
    }
};
