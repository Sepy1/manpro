<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dc_drc_devices', function (Blueprint $table) {
            $table->string('objid_cpu')->nullable()->after('storage_gb');
            $table->string('objid_ram')->nullable()->after('objid_cpu');
            $table->string('objid_ping')->nullable()->after('objid_ram');
            $table->string('objid_diskfree')->nullable()->after('objid_ping');
        });
    }

    public function down(): void
    {
        Schema::table('dc_drc_devices', function (Blueprint $table) {
            $table->dropColumn([
                'objid_cpu',
                'objid_ram',
                'objid_ping',
                'objid_diskfree',
            ]);
        });
    }
};
