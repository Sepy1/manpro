<?php

namespace App\Services;

use App\Models\UserAccessLog;
use App\Models\VpnClient;
use App\Models\VpnConnection;
use Illuminate\Support\Carbon;

class VpnConnectionSyncService
{
    public function __construct(private readonly OpenVpnService $openVpn) {}

    /** @return array{active:int,connected:int,disconnected:int} */
    public function sync(): array
    {
        $seen = [];
        $connected = 0;

        foreach ($this->openVpn->connectedClients() as $row) {
            $client = VpnClient::where('common_name', $row['common_name'])->first();
            $connection = VpnConnection::where('common_name', $row['common_name'])
                ->whereNull('disconnected_at')->first();

            if (! $connection) {
                $connection = VpnConnection::create(array_merge($row, ['vpn_client_id' => $client?->id]));
                $this->writeAccessLog($connection, $client, 'login');
                $connected++;
            } else {
                $connection->update([
                    'real_ip' => $row['real_ip'], 'vpn_ip' => $row['vpn_ip'],
                    'bytes_received' => $row['bytes_received'], 'bytes_sent' => $row['bytes_sent'],
                ]);
                // Backfill login logs for sessions created before access-log integration.
                // firstOrCreate keeps subsequent scheduler runs idempotent.
                $this->writeAccessLog($connection, $client, 'login');
            }

            $client?->update(['last_connected_at' => now()]);
            $seen[] = $connection->id;
        }

        $disconnected = 0;
        VpnConnection::with('client')->whereNull('disconnected_at')
            ->when($seen, fn ($query) => $query->whereNotIn('id', $seen))
            ->get()->each(function (VpnConnection $connection) use (&$disconnected): void {
                $connection->update(['disconnected_at' => now(), 'disconnect_reason' => 'status_sync']);
                $this->writeAccessLog($connection, $connection->client, 'logout');
                $disconnected++;
            });

        return ['active' => count($seen), 'connected' => $connected, 'disconnected' => $disconnected];
    }

    private function writeAccessLog(VpnConnection $connection, ?VpnClient $client, string $action): void
    {
        $occurredAt = $action === 'login' ? $connection->connected_at : $connection->disconnected_at;
        $occurredAt = ($occurredAt instanceof Carbon ? $occurredAt : Carbon::parse($occurredAt ?? now()))
            ->timezone(config('app.timezone'));
        $username = $client?->username ?: $connection->common_name;
        $logId = "openvpn-{$action}-{$connection->id}";

        UserAccessLog::firstOrCreate(['log_id' => $logId], [
            'vendor' => 'OpenVPN',
            'device_name' => 'OpenVPN Server',
            'device_id' => 'openvpn-server',
            'virtual_domain' => null,
            'event_type' => "vpn_{$action}",
            'event_category' => 'vpn_access',
            'username' => $username,
            'auth_server' => $client?->mfa_enabled_at ? 'OpenVPN + Google Authenticator' : 'OpenVPN',
            'source_ip' => $connection->real_ip,
            'destination_ip' => config('openvpn.server_ip'),
            'tunnel_ip' => $connection->vpn_ip,
            'source_interface' => 'openvpn',
            'destination_interface' => 'tun0',
            'method' => 'openvpn',
            'action' => $action,
            'status' => 'success',
            'original_status' => $action === 'login' ? 'connected' : 'disconnected',
            'reason' => $action === 'logout' ? ($connection->disconnect_reason ?: 'disconnected') : null,
            'profile' => $client?->group?->name,
            'event_date' => $occurredAt->toDateString(),
            'event_clock' => $occurredAt->format('H:i:s'),
            'timezone' => $occurredAt->format('O'),
            'event_time' => $occurredAt->toIso8601String(),
            'log_description' => $action === 'login' ? 'User OpenVPN connected' : 'User OpenVPN disconnected',
            'severity' => 'information',
            'message' => $action === 'login'
                ? "User {$username} login OpenVPN dari {$connection->real_ip} dengan IP tunnel {$connection->vpn_ip}"
                : "User {$username} logout OpenVPN dari IP tunnel {$connection->vpn_ip}",
            'raw_log' => json_encode([
                'connection_id' => $connection->id, 'common_name' => $connection->common_name,
                'real_ip' => $connection->real_ip, 'vpn_ip' => $connection->vpn_ip,
                'connected_at' => $connection->connected_at?->toIso8601String(),
                'disconnected_at' => $connection->disconnected_at?->toIso8601String(),
                'bytes_received' => $connection->bytes_received, 'bytes_sent' => $connection->bytes_sent,
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }
}
