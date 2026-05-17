<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dc_drc_devices', function (Blueprint $table) {
            $table->string('objid_traffic')->nullable()->after('objid_diskfree');
        });
    }

    public function down(): void
    {
        Schema::table('dc_drc_devices', function (Blueprint $table) {
            $table->dropColumn('objid_traffic');
        });
    }
};
