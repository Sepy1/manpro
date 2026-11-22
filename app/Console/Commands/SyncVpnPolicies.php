<?php

namespace App\Console\Commands;

use App\Models\VpnClient;
use App\Services\OpenVpnService;
use Illuminate\Console\Command;

class SyncVpnPolicies extends Command
{
    protected $signature = 'vpn:sync-policies {--apply : Terapkan policy ke firewall server VPN}';

    protected $description = 'Audit atau sinkronkan seluruh access policy user VPN';

    public function handle(OpenVpnService $openVpn): int
    {
        $clients = VpnClient::with('group')->orderBy('vpn_ip')->get();
        if ($clients->isEmpty()) {
            $this->info('Belum ada user VPN terdaftar.');

            return self::SUCCESS;
        }

        $rows = $clients->map(function (VpnClient $client) {
            $destinations = $client->effectiveAllowedDestinations();

            return [
                $client->name,
                $client->vpn_ip,
                $client->status,
                $client->isWithinAccessSchedule() ? 'diizinkan' : 'di luar jadwal',
                ! empty($client->allowed_destinations) ? 'user' : (! empty($client->group?->allowed_destinations) ? 'group' : 'default-deny'),
                $destinations === [] ? 'BLOCK ALL' : implode(', ', $destinations),
            ];
        });
        $this->table(['User', 'IP VPN', 'Status', 'Jadwal', 'Sumber', 'Allowed destination'], $rows);

        if (! $this->option('apply')) {
            $this->warn('Mode audit saja. Gunakan --apply untuk menerapkan policy.');

            return self::SUCCESS;
        }

        foreach ($clients as $client) {
            try {
                $openVpn->syncAccessPolicy($client);
                $this->components->task("Sinkronisasi {$client->vpn_ip}");
            } catch (\Throwable $e) {
                $this->error("{$client->vpn_ip}: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->info('Seluruh policy VPN berhasil disinkronkan.');

        return self::SUCCESS;
    }
}
