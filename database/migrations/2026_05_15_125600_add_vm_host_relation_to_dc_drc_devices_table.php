<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dc_drc_devices', function (Blueprint $table) {
            $table->foreignId('vm_host_id')
                ->nullable()
                ->after('host_server')
                ->constrained('dc_drc_devices')
                ->nullOnDelete();
        });

        // Backfill relation based on existing host_server text.
        DB::statement("
            UPDATE dc_drc_devices vm
            JOIN dc_drc_devices host ON LOWER(vm.host_server) = LOWER(host.server_name)
            SET vm.vm_host_id = host.id
            WHERE LOWER(vm.device_type) = 'vm' AND LOWER(host.device_type) = 'vm host'
        ");
    }

    public function down(): void
    {
        Schema::table('dc_drc_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vm_host_id');
        });
    }
};
