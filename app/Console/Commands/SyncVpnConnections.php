<?php

namespace App\Console\Commands;

use App\Services\VpnConnectionSyncService;
use Illuminate\Console\Command;

class SyncVpnConnections extends Command
{
    protected $signature = 'vpn:sync-connections';

    protected $description = 'Sinkronkan login/logout OpenVPN ke riwayat koneksi dan log akses user';

    public function handle(VpnConnectionSyncService $syncService): int
    {
        try {
            $result = $syncService->sync();
            $this->info("Sinkronisasi selesai: {$result['connected']} login, {$result['disconnected']} logout.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
