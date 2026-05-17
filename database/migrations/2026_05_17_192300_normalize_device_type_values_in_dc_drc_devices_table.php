<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dc_drc_devices')
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(device_type, ' ', ''), '-', ''), '_', '')) = 'baremetal'")
            ->update(['device_type' => 'bare metal']);

        DB::table('dc_drc_devices')
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(device_type, ' ', ''), '-', ''), '_', '')) = 'vmhost'")
            ->update(['device_type' => 'vm host']);

        DB::table('dc_drc_devices')
            ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(device_type, ' ', ''), '-', ''), '_', '')) = 'vm'")
            ->update(['device_type' => 'VM']);
    }

    public function down(): void
    {
        // Intentionally left blank because normalization is irreversible.
    }
};
