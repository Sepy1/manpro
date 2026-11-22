<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VpnGroup;
use App\Services\OpenVpnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VpnGroupController extends Controller
{
    public function __construct(private readonly OpenVpnService $openVpn) {}

    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.vpn.groups', ['groups' => VpnGroup::withCount('clients')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $data = $this->validated($request);
        VpnGroup::create($data);

        return back()->with('success', 'Group VPN berhasil dibuat.');
    }

    public function update(Request $request, VpnGroup $vpnGroup): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $vpnGroup->update($this->validated($request, $vpnGroup));
        try {
            foreach ($vpnGroup->clients()->with('group')->get() as $client) {
                $this->openVpn->syncAccessPolicy($client);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['vpn' => 'Group tersimpan, tetapi sinkronisasi firewall gagal: '.$e->getMessage()]);
        }

        return back()->with('success', 'Group dan policy firewall berhasil diperbarui.');
    }

    public function destroy(VpnGroup $vpnGroup): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        if ($vpnGroup->clients()->exists()) {
            return back()->withErrors(['vpn' => 'Group masih digunakan oleh user VPN. Pindahkan user terlebih dahulu.']);
        }
        $vpnGroup->delete();

        return back()->with('success', 'Group VPN berhasil dihapus.');
    }

    private function validated(Request $request, ?VpnGroup $group = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('vpn_groups')->ignore($group)],
            'description' => ['nullable', 'string', 'max:1000'],
            'allowed_destinations_text' => ['nullable', 'string', 'max:5000'],
            'schedule_enabled' => ['nullable', 'boolean'],
            'schedule_days' => ['nullable', 'array', 'min:1'],
            'schedule_days.*' => ['integer', 'between:1,7'],
            'schedule_start' => ['nullable', 'date_format:H:i'],
            'schedule_end' => ['nullable', 'date_format:H:i', 'different:schedule_start'],
        ]);
        $data['allowed_destinations'] = $this->destinations($data['allowed_destinations_text'] ?? '');
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

    private function destinations(string $value): array
    {
        $items = array_values(array_unique(array_filter(preg_split('/[\s,]+/', trim($value)))));
        foreach ($items as $item) {
            [$ip, $prefix] = array_pad(explode('/', $item, 2), 2, null);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || ($prefix !== null && (! ctype_digit($prefix) || (int) $prefix > 32))) {
                abort(422, "Allowed IP/CIDR tidak valid: {$item}");
            }
        }

        return $items;
    }
}
