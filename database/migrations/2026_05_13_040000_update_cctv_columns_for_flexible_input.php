<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE cctv_devices MODIFY channel_count INT UNSIGNED NULL');
        DB::statement('ALTER TABLE cctv_devices MODIFY connection_status VARCHAR(255) NULL');
        DB::statement('ALTER TABLE cctv_devices MODIFY device_status VARCHAR(255) NULL');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement("UPDATE cctv_devices SET channel_count = 1 WHERE channel_count IS NULL");
        DB::statement("UPDATE cctv_devices SET connection_status = 'online' WHERE connection_status IS NULL OR connection_status = ''");
        DB::statement("UPDATE cctv_devices SET device_status = 'normal' WHERE device_status IS NULL OR device_status = ''");

        DB::statement('ALTER TABLE cctv_devices MODIFY channel_count INT UNSIGNED NOT NULL');
        DB::statement("ALTER TABLE cctv_devices MODIFY connection_status ENUM('online','offline') NOT NULL DEFAULT 'online'");
        DB::statement("ALTER TABLE cctv_devices MODIFY device_status ENUM('normal','perlu_perbaikan') NOT NULL DEFAULT 'normal'");
    }
};
