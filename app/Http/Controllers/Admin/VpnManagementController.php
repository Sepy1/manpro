<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\VpnClientsExport;
use App\Exports\VpnClientsTemplateExport;
use App\Imports\VpnClientsImport;
use App\Models\VpnAdminActivity;
use App\Models\VpnClient;
use App\Models\VpnConnection;
use App\Models\VpnGroup;
use App\Services\OpenVpnService;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VpnManagementController extends Controller
{
    public function __construct(private readonly OpenVpnService $openVpn) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin();
        $this->syncConnections();
        $clients = VpnClient::query()->with(['group', 'connections' => fn ($query) => $query->latest('connected_at')->limit(10)])->withCount('connections')->latest()->paginate(15)->withQueryString();
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
            ->with(['group', 'connections' => fn ($query) => $query->latest('connected_at')->limit(10)])
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
            'groups' => VpnGroup::orderBy('name')->get(),
        ]);
    }

    public function exportUsers()
    {
        $this->authorizeAdmin();

        return Excel::download(new VpnClientsExport, 'user-vpn-'.now()->format('Ymd-His').'.xlsx');
    }

    public function importTemplate()
    {
        $this->authorizeAdmin();

        return Excel::download(new VpnClientsTemplateExport, 'template-import-user-vpn.xlsx');
    }

    public function importUsers(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $request->validate(['import_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']]);

        try {
            $import = new VpnClientsImport($this->openVpn, (int) auth()->id());
            Excel::import($import, $request->file('import_file'));
            VpnAdminActivity::create([
                'user_id' => auth()->id(), 'action' => 'clients_bulk_imported',
                'description' => "Import bulk selesai: {$import->imported} berhasil, ".count($import->errors).' gagal',
                'ip_address' => $request->ip(),
            ]);

            $response = back()->with('success', "Import selesai: {$import->imported} user berhasil dibuat.");
            if ($import->errors !== []) {
                $response->with('import_errors', $import->errors);
            }

            return $response;
        } catch (\Throwable $e) {
            return back()->withErrors(['import_file' => $e->getMessage()]);
        }
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
                $this->openVpn->syncAccessPolicy($client);
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
        if (($vpnClient->certificate_status === 'active' || $vpnClient->mfa_enabled_at !== null) && ($data['common_name'] ?? $vpnClient->common_name) !== $vpnClient->common_name) {
            return back()->withErrors(['common_name' => 'Common Name tidak dapat diubah saat sertifikat atau 2FA aktif. Revoke sertifikat dan nonaktifkan 2FA terlebih dahulu.']);
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
                $this->openVpn->syncAccessPolicy($vpnClient);
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
            $this->openVpn->syncAccessPolicy($vpnClient);
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
            $this->openVpn->syncAccessPolicy($vpnClient);
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

    public function enrollMfa(Request $request, VpnClient $vpnClient): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $enrollment = $this->openVpn->enrollMfa($vpnClient);
            $wasEnabled = $vpnClient->mfa_enabled_at !== null;
            $vpnClient->update([
                'mfa_enabled_at' => now(),
                'mfa_last_reset_at' => $wasEnabled ? now() : null,
            ]);
            $this->log($wasEnabled ? 'mfa_reset' : 'mfa_enrolled', $vpnClient, $wasEnabled ? 'Google Authenticator direset' : 'Google Authenticator diaktifkan', $request);

            return back()->with('mfa_enrollment', [
                'client_name' => $vpnClient->name,
                'common_name' => $vpnClient->common_name,
                'qr_data_uri' => (new Builder)->build(data: $enrollment['otpauth_uri'], size: 260, margin: 8)->getDataUri(),
                'recovery_codes' => $enrollment['recovery_codes'],
            ])->with('success', $wasEnabled ? '2FA berhasil direset. QR lama tidak berlaku.' : '2FA berhasil dibuat. Pindai QR sebelum menutup halaman ini.');
        } catch (\Throwable $e) {
            return back()->withErrors(['vpn' => $e->getMessage()]);
        }
    }

    public function disableMfa(Request $request, VpnClient $vpnClient): RedirectResponse
    {
        $this->authorizeAdmin();
        try {
            $this->openVpn->disableMfa($vpnClient);
            $vpnClient->update(['mfa_enabled_at' => null, 'mfa_last_reset_at' => now()]);
            $this->log('mfa_disabled', $vpnClient, 'Google Authenticator dinonaktifkan; koneksi VPN akan ditolak sampai enrollment baru', $request);

            return back()->with('success', '2FA dinonaktifkan. User tidak dapat terhubung sampai 2FA dibuat kembali.');
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
            if ($vpnClient->mfa_enabled_at !== null) {
                $this->openVpn->disableMfa($vpnClient);
            }
            $this->openVpn->deleteCcd($vpnClient);
            $vpnClient->status = 'disabled';
            $this->openVpn->syncAccessPolicy($vpnClient);
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', Rule::unique('vpn_clients')->ignore($client)],
            'common_name' => ['required', 'regex:/\A[A-Za-z0-9][A-Za-z0-9_.-]{0,127}\z/', Rule::unique('vpn_clients')->ignore($client)],
            'department' => ['nullable', 'string', 'max:255'], 'branch_office' => ['nullable', 'string', 'max:255'],
            'inventory_number' => ['nullable', 'string', 'max:100'],
            'vpn_group_id' => ['nullable', 'integer', 'exists:vpn_groups,id'],
            'allowed_destinations_text' => ['nullable', 'string', 'max:5000'],
            'schedule_enabled' => ['nullable', 'boolean'],
            'schedule_days' => ['nullable', 'array', 'min:1'],
            'schedule_days.*' => ['integer', 'between:1,7'],
            'schedule_start' => ['nullable', 'date_format:H:i'],
            'schedule_end' => ['nullable', 'date_format:H:i', 'different:schedule_start'],
            'vpn_ip' => ['nullable', 'ipv4', Rule::unique('vpn_clients')->ignore($client), function ($attribute, $value, $fail) {
                if ($value && (! str_starts_with($value, '10.200.0.') || in_array($value, ['10.200.0.0', '10.200.0.1', '10.200.0.255']))) {
                    $fail('IP VPN harus host 10.200.0.2–10.200.0.254.');
                }
            }],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);
        $data['allowed_destinations'] = $this->parseDestinations($data['allowed_destinations_text'] ?? '');
        $scheduleEnabled = (bool) ($data['schedule_enabled'] ?? false);
        if ($scheduleEnabled) {
            validator($data, [
                'schedule_days' => ['required', 'array', 'min:1'],
                'schedule_start' => ['required', 'date_format:H:i'],
                'schedule_end' => ['required', 'date_format:H:i', 'different:schedule_start'],
            ])->validate();
            $data['access_schedule'] = [
                'days' => array_values(array_unique(array_map('intval', $data['schedule_days']))),
                'start' => $data['schedule_start'],
                'end' => $data['schedule_end'],
            ];
        } else {
            $data['access_schedule'] = null;
        }
        unset($data['allowed_destinations_text'], $data['schedule_enabled'], $data['schedule_days'], $data['schedule_start'], $data['schedule_end']);

        return $data;
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

    private function parseDestinations(string $value): array
    {
        $items = array_values(array_unique(array_filter(preg_split('/[\s,]+/', trim($value)))));
        foreach ($items as $item) {
            [$ip, $prefix] = array_pad(explode('/', $item, 2), 2, null);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || ($prefix !== null && (! ctype_digit($prefix) || (int) $prefix > 32))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['allowed_destinations_text' => "Allowed IP/CIDR tidak valid: {$item}"]);
            }
        }

        return $items;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }
}
