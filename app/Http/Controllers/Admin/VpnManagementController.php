<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VpnAdminActivity;
use App\Models\VpnClient;
use App\Models\VpnConnection;
use App\Services\OpenVpnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VpnManagementController extends Controller
{
    public function __construct(private readonly OpenVpnService $openVpn) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin();
        $this->syncConnections();
        $clients = VpnClient::query()->with(['connections' => fn ($query) => $query->latest('connected_at')->limit(10)])->withCount('connections')->latest()->paginate(15)->withQueryString();
        $onlineIds = VpnConnection::whereNull('disconnected_at')->whereNotNull('vpn_client_id')->pluck('vpn_client_id')->all();
        $days = collect(range(6, 0))->map(fn ($ago) => now()->subDays($ago));
        $connectionsChart = $days->map(fn ($day) => VpnConnection::whereBetween('connected_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->count());
        $durationChart = $days->map(function ($day) {
            $rows = VpnConnection::whereBetween('connected_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->get(['connected_at', 'disconnected_at']);

            return round((float) $rows->avg(fn ($row) => $row->connected_at->diffInMinutes($row->disconnected_at ?? now())), 1);
        });
        try {
            $topology = $this->openVpn->topology();
        } catch (\Throwable $e) {
            $topology = null;
            $topologyError = $e->getMessage();
        }

        return view('pages.dashboard.vpn.index', [
            'clients' => $clients,
            'onlineIds' => $onlineIds,
            'summary' => [
                'total' => VpnClient::count(),
                'online' => count($onlineIds),
                'offline' => max(0, VpnClient::where('status', 'active')->count() - count($onlineIds)),
                'today' => VpnConnection::whereDate('connected_at', today())->count(),
                'certActive' => VpnClient::where('certificate_status', 'active')->count(),
                'certRevoked' => VpnClient::where('certificate_status', 'revoked')->count(),
                'firewall' => $this->openVpn->firewallRuleCount(),
            ],
            'serverStatus' => $this->openVpn->serverStatus(),
            'topology' => $topology,
            'topologyError' => $topologyError ?? null,
            'activities' => VpnAdminActivity::with(['user', 'client'])->latest()->limit(8)->get(),
            'chart' => ['labels' => $days->map->format('d M'), 'connections' => $connectionsChart, 'durations' => $durationChart],
        ]);
    }

    public function users(Request $request): View
    {
        $this->authorizeAdmin();
        $this->syncConnections();

        $keyword = trim((string) $request->query('keyword', ''));
        $status = (string) $request->query('status', '');
        $certificate = (string) $request->query('certificate', '');

        $clients = VpnClient::query()
            ->with(['connections' => fn ($query) => $query->latest('connected_at')->limit(10)])
            ->withCount('connections')
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('common_name', 'like', "%{$keyword}%")
                        ->orWhere('vpn_ip', 'like', "%{$keyword}%")
                        ->orWhere('department', 'like', "%{$keyword}%")
                        ->orWhere('branch_office', 'like', "%{$keyword}%");
                });
            })
            ->when(in_array($status, ['active', 'disabled'], true), fn ($query) => $query->where('status', $status))
            ->when(in_array($certificate, ['not_generated', 'active', 'revoked'], true), fn ($query) => $query->where('certificate_status', $certificate))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('pages.dashboard.vpn.users', [
            'clients' => $clients,
            'onlineIds' => VpnConnection::whereNull('disconnected_at')->whereNotNull('vpn_client_id')->pluck('vpn_client_id')->all(),
            'filters' => compact('keyword', 'status', 'certificate'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $this->validated($request);
        try {
            DB::transaction(function () use (&$data, $request) {
                $this->openVpn->topology();
                $data['vpn_ip'] = $data['vpn_ip'] ?: $this->nextIp();
                $data['created_by'] = auth()->id();
                $client = VpnClient::create($data);
                $this->openVpn->writeCcd($client);
                $this->log('client_created', $client, "Client {$client->common_name} dibuat dengan IP {$client->vpn_ip}", $request);
            });

            return back()->with('success', 'Client VPN berhasil dibuat dan CCD telah ditulis.');
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['vpn' => $e->getMessage()]);
        }
    }

    public function update(Request $request, VpnClient $vpnClient): RedirectResponse
    {
        $this->authorizeAdmin();
        $data = $this->validated($request, $vpnClient);
        if ($vpnClient->certificate_status === 'active' && ($data['common_name'] ?? $vpnClient->common_name) !== $vpnClient->common_name) {
            return back()->withErrors(['common_name' => 'Common Name tidak dapat diubah saat sertifikat aktif. Revoke sertifikat terlebih dahulu.']);
        }
        try {
            $oldCn = $vpnClient->common_name;
            DB::transaction(function () use ($data, $vpnClient, $request, $oldCn) {
                $vpnClient->update($data);
                if ($oldCn !== $vpnClient->common_name) {
                    $old = clone $vpnClient;
                    $old->common_name = $oldCn;
                    $this->openVpn->deleteCcd($old);
                }
                $this->openVpn->writeCcd($vpnClient);
                $this->log('client_updated', $vpnClient, 'Informasi client dan CCD diperbarui', $request);
            });

            return back()->with('success', 'Client VPN berhasil diperbarui.');
        } catch (\Throwable $e) {
            return back()->withErrors(['vpn' => $e->getMessage()]);
        }
    }

    public function toggle(Request $request, VpnClient $vpnClient): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $newStatus = $vpnClient->status === 'active' ? 'disabled' : 'active';
            $vpnClient->update(['status' => $newStatus]);
            if ($newStatus === 'disabled') {
                $this->openVpn->deleteCcd($vpnClient);
            } else {
                $this->openVpn->writeCcd($vpnClient);
            }
            $this->log('client_'.$newStatus, $vpnClient, "Client diubah menjadi {$newStatus}", $request);

            return back()->with('success', "Client berhasil diubah menjadi {$newStatus}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['vpn' => $e->getMessage()]);
        }
    }

    public function certificate(Request $request, VpnClient $vpnClient): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $this->openVpn->generateCertificate($vpnClient);
            $vpnClient->update(['certificate_status' => 'active', 'certificate_issued_at' => today(), 'certificate_expires_at' => today()->addDays(825)]);
            $this->log('certificate_generated', $vpnClient, 'Sertifikat client dibuat', $request);

            return back()->with('success', 'Sertifikat client berhasil dibuat.');
        } catch (\Throwable $e) {
            return back()->withErrors(['vpn' => $e->getMessage()]);
        }
    }

    public function revoke(Request $request, VpnClient $vpnClient): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $this->openVpn->revokeCertificate($vpnClient);
            $this->openVpn->deleteCcd($vpnClient);
            $vpnClient->update(['certificate_status' => 'revoked', 'status' => 'disabled']);
            $this->log('certificate_revoked', $vpnClient, 'Sertifikat direvoke dan client dinonaktifkan', $request);

            return back()->with('success', 'Sertifikat berhasil direvoke.');
        } catch (\Throwable $e) {
            return back()->withErrors(['vpn' => $e->getMessage()]);
        }
    }

    public function download(Request $request, VpnClient $vpnClient): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $path = $this->openVpn->profilePath($vpnClient);
            $path = $this->openVpn->generateProfile($vpnClient);
            $this->log('profile_downloaded', $vpnClient, 'Profil .ovpn diunduh', $request);

            return response()->download($path, $vpnClient->common_name.'.ovpn', ['Content-Type' => 'application/x-openvpn-profile']);
        } catch (\Throwable $e) {
            return back()->withErrors(['vpn' => $e->getMessage()]);
        }
    }

    public function restartServer(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $this->openVpn->restartServer();
            VpnAdminActivity::create(['user_id' => auth()->id(), 'action' => 'server_restarted', 'description' => 'Service OpenVPN direstart', 'ip_address' => $request->ip()]);

            return back()->with('success', 'Service OpenVPN berhasil direstart.');
        } catch (\Throwable $e) {
            return back()->withErrors(['vpn' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, VpnClient $vpnClient): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            if ($vpnClient->certificate_status === 'active') {
                $this->openVpn->revokeCertificate($vpnClient);
            }
            $this->openVpn->deleteCcd($vpnClient);
            $name = $vpnClient->common_name;
            $vpnClient->delete();
            VpnAdminActivity::create(['user_id' => auth()->id(), 'action' => 'client_deleted', 'description' => "Client {$name} dihapus", 'ip_address' => $request->ip()]);

            return back()->with('success', 'Client dihapus; sertifikat aktif telah direvoke.');
        } catch (\Throwable $e) {
            return back()->withErrors(['vpn' => $e->getMessage()]);
        }
    }

    private function validated(Request $request, ?VpnClient $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', Rule::unique('vpn_clients')->ignore($client)],
            'common_name' => ['required', 'regex:/\A[A-Za-z0-9][A-Za-z0-9_.-]{0,127}\z/', Rule::unique('vpn_clients')->ignore($client)],
            'department' => ['nullable', 'string', 'max:255'], 'branch_office' => ['nullable', 'string', 'max:255'],
            'inventory_number' => ['nullable', 'string', 'max:100'],
            'vpn_ip' => ['nullable', 'ipv4', Rule::unique('vpn_clients')->ignore($client), function ($attribute, $value, $fail) {
                if ($value && (! str_starts_with($value, '10.200.0.') || in_array($value, ['10.200.0.0', '10.200.0.1', '10.200.0.255']))) {
                    $fail('IP VPN harus host 10.200.0.2–10.200.0.254.');
                }
            }],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);
    }

    private function nextIp(): string
    {
        $used = VpnClient::lockForUpdate()->pluck('vpn_ip')->flip();
        for ($i = 2; $i <= 254; $i++) {
            if (! isset($used["10.200.0.{$i}"])) {
                return "10.200.0.{$i}";
            }
        }
        throw new \RuntimeException('Pool IP VPN 10.200.0.0/24 telah penuh.');
    }

    private function syncConnections(): void
    {
        try {
            $seen = [];
            foreach ($this->openVpn->connectedClients() as $row) {
                $client = VpnClient::where('common_name', $row['common_name'])->first();
                $connection = VpnConnection::where('common_name', $row['common_name'])->whereNull('disconnected_at')->first();
                if (! $connection) {
                    $connection = VpnConnection::create(array_merge($row, ['vpn_client_id' => $client?->id]));
                } else {
                    $connection->update(['real_ip' => $row['real_ip'], 'vpn_ip' => $row['vpn_ip'], 'bytes_received' => $row['bytes_received'], 'bytes_sent' => $row['bytes_sent']]);
                }
                if ($client) {
                    $client->update(['last_connected_at' => now()]);
                }
                $seen[] = $connection->id;
            }
            VpnConnection::whereNull('disconnected_at')->when($seen, fn ($query) => $query->whereNotIn('id', $seen))->update(['disconnected_at' => now(), 'disconnect_reason' => 'status_sync']);
        } catch (\Throwable) {
            // Dashboard tetap tersedia ketika status log belum dapat dibaca.
        }
    }

    private function log(string $action, VpnClient $client, string $description, Request $request): void
    {
        VpnAdminActivity::create(['user_id' => auth()->id(), 'vpn_client_id' => $client->id, 'action' => $action, 'description' => $description, 'ip_address' => $request->ip()]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }
}
