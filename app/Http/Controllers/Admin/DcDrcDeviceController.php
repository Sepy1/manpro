<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DcDrcDevicesExport;
use App\Exports\DcDrcTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\DcDrcDevicesImport;
use App\Models\DcDrcDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException as FormValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class DcDrcDeviceController extends Controller
{
    /**
     * @param  array<int, string|int|float>  $labels
     * @param  array<int, string|int|float>  $totals
     * @return array{gradient: string, items: list<array{label: string|int|float, total: int, percent: float, color: string}>, sum: int}
     */
    public static function buildConicDonut(array $labels, array $totals, array $palette): array
    {
        $sum = array_sum($totals);

        if ($sum <= 0) {
            return [
                'gradient' => '#1f2937 0% 100%',
                'items' => [],
                'sum' => 0,
            ];
        }

        $current = 0.0;
        $segments = [];
        $items = [];

        foreach ($labels as $idx => $label) {
            $value = (float) ($totals[$idx] ?? 0);
            if ($value <= 0) {
                continue;
            }

            $percent = ($value / $sum) * 100;
            $start = $current;
            $end = $current + $percent;
            $color = $palette[$idx % count($palette)];
            $segments[] = sprintf('%s %.3f%% %.3f%%', $color, $start, $end);
            $items[] = [
                'label' => $label,
                'total' => (int) $value,
                'percent' => round($percent, 1),
                'color' => $color,
            ];
            $current = $end;
        }

        if ($segments === []) {
            return [
                'gradient' => '#1f2937 0% 100%',
                'items' => [],
                'sum' => 0,
            ];
        }

        return [
            'gradient' => implode(', ', $segments),
            'items' => $items,
            'sum' => (int) $sum,
        ];
    }

    /**
     * @return array{hostTypeDonut: array, brandDonut: array, vmPerHostDonut: array, osDonut: array}
     */
    public function dashboardDonuts(): array
    {
        $allDevices = DcDrcDevice::query()->get(['device_type', 'nic_model']);

        $hostTypeStats = [
            'Baremetal' => 0,
            'VM Host' => 0,
        ];

        foreach ($allDevices as $device) {
            $type = strtolower(trim((string) $device->device_type));
            if (in_array($type, ['baremetal', 'bare metal'], true)) {
                $hostTypeStats['Baremetal']++;
                continue;
            }

            if ($type === 'vm host') {
                $hostTypeStats['VM Host']++;
            }
        }

        $brandRows = DcDrcDevice::query()
            ->selectRaw("CASE WHEN nic_model IS NULL OR TRIM(nic_model) = '' THEN 'Tidak Diketahui' ELSE nic_model END as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $osRows = DcDrcDevice::query()
            ->selectRaw("CASE WHEN os IS NULL OR TRIM(os) = '' THEN 'Tidak Diketahui' ELSE TRIM(os) END as label, COUNT(*) as total")
            ->groupByRaw("CASE WHEN os IS NULL OR TRIM(os) = '' THEN 'Tidak Diketahui' ELSE TRIM(os) END")
            ->orderByDesc('total')
            ->get();

        $vmPerHostRows = DcDrcDevice::query()
            ->from('dc_drc_devices as host')
            ->leftJoin('dc_drc_devices as vm', function ($join): void {
                $join->on('vm.vm_host_id', '=', 'host.id')
                    ->whereRaw("LOWER(vm.device_type) = 'vm'");
            })
            ->whereRaw("LOWER(host.device_type) = 'vm host'")
            ->groupBy('host.id', 'host.server_name')
            ->orderByDesc('total')
            ->selectRaw('host.server_name as label, COUNT(vm.id) as total')
            ->get();

        $hostPalette = ['#06b6d4', '#8b5cf6'];
        $brandPalette = ['#10b981', '#06b6d4', '#8b5cf6', '#f59e0b', '#ef4444', '#3b82f6', '#22c55e', '#a855f7', '#14b8a6', '#f97316'];
        $vmPalette = ['#22c55e', '#06b6d4', '#8b5cf6', '#f59e0b', '#ef4444', '#3b82f6'];
        $osPalette = ['#0ea5e9', '#d946ef', '#eab308', '#34d399', '#fb7185', '#818cf8', '#2dd4bf', '#f97316', '#a78bfa', '#4ade80'];

        return [
            'hostTypeDonut' => self::buildConicDonut(
                array_keys($hostTypeStats),
                array_values($hostTypeStats),
                $hostPalette
            ),
            'brandDonut' => self::buildConicDonut(
                $brandRows->pluck('label')->values()->all(),
                $brandRows->pluck('total')->values()->all(),
                $brandPalette
            ),
            'vmPerHostDonut' => self::buildConicDonut(
                $vmPerHostRows->pluck('label')->values()->all(),
                $vmPerHostRows->pluck('total')->values()->all(),
                $vmPalette
            ),
            'osDonut' => self::buildConicDonut(
                $osRows->pluck('label')->values()->all(),
                $osRows->pluck('total')->values()->all(),
                $osPalette
            ),
        ];
    }

    public function dashboard(): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return redirect()->route('admin.aset-ti.data-center', ['tab' => 'dc-drc']);
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $allowedSorts = [
            'server_name',
            'device_type',
            'host_server',
            'ip_address',
            'vlan',
            'nic_model',
            'os',
            'cpu_cores',
            'ram_gb',
            'storage_gb',
            'objid_cpu',
            'objid_ram',
            'objid_ping',
            'objid_diskfree',
            'objid_traffic',
            'site',
            'system_role',
            'environment',
            'owner_team',
            'status',
            'created_at',
        ];

        $sortBy = request('sort_by', 'created_at');
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower((string) request('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $keyword = trim((string) request('keyword', ''));

        $devicesQuery = DcDrcDevice::query()
            ->with([
                'vmHost:id,server_name,device_type',
                'hostedVms' => function ($query): void {
                    $query->whereRaw("LOWER(device_type) = 'vm'")
                        ->orderBy('server_name');
                },
            ])
            ->orderBy($sortBy, $sortDir);

        if ($keyword !== '') {
            $matched = DcDrcDevice::query()
                ->where(function ($inner) use ($keyword): void {
                    $inner->where('server_name', 'like', "%{$keyword}%")
                        ->orWhere('device_type', 'like', "%{$keyword}%")
                        ->orWhere('host_server', 'like', "%{$keyword}%")
                        ->orWhere('ip_address', 'like', "%{$keyword}%")
                        ->orWhere('vlan', 'like', "%{$keyword}%")
                        ->orWhere('nic_model', 'like', "%{$keyword}%")
                        ->orWhere('os', 'like', "%{$keyword}%")
                        ->orWhere('objid_cpu', 'like', "%{$keyword}%")
                        ->orWhere('objid_ram', 'like', "%{$keyword}%")
                        ->orWhere('objid_ping', 'like', "%{$keyword}%")
                        ->orWhere('objid_diskfree', 'like', "%{$keyword}%")
                        ->orWhere('objid_traffic', 'like', "%{$keyword}%")
                        ->orWhere('site', 'like', "%{$keyword}%")
                        ->orWhere('system_role', 'like', "%{$keyword}%")
                        ->orWhere('environment', 'like', "%{$keyword}%")
                        ->orWhere('owner_team', 'like', "%{$keyword}%")
                        ->orWhere('status', 'like', "%{$keyword}%")
                        ->orWhere('notes', 'like', "%{$keyword}%");
                })
                ->get(['id', 'device_type', 'vm_host_id']);

            $hostIds = collect();
            $standaloneIds = collect();

            foreach ($matched as $device) {
                $type = strtolower(trim((string) $device->device_type));

                if ($type === 'vm host') {
                    $hostIds->push((int) $device->id);
                    continue;
                }

                if ($type === 'vm' && !empty($device->vm_host_id)) {
                    $hostIds->push((int) $device->vm_host_id);
                    continue;
                }

                $standaloneIds->push((int) $device->id);
            }

            $hostIds = $hostIds->filter()->unique()->values();
            $standaloneIds = $standaloneIds->filter()->unique()->values();

            $devicesQuery->where(function ($query) use ($hostIds, $standaloneIds): void {
                if ($hostIds->isNotEmpty()) {
                    $query->whereIn('id', $hostIds)
                        ->orWhereIn('vm_host_id', $hostIds);
                }

                if ($standaloneIds->isNotEmpty()) {
                    if ($hostIds->isNotEmpty()) {
                        $query->orWhereIn('id', $standaloneIds);
                    } else {
                        $query->whereIn('id', $standaloneIds);
                    }
                }
            });
        }

        $devices = $devicesQuery->get();

        return view('pages.dashboard.aset-ti.perangkat-dc-drc', [
            'devices' => $devices,
            'vmHosts' => DcDrcDevice::query()
                ->whereRaw("LOWER(device_type) = 'vm host'")
                ->orderBy('server_name')
                ->get(['id', 'server_name']),
            'filters' => ['keyword' => $keyword],
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        DcDrcDevice::create($this->preparePayload($request));

        return redirect()->route('admin.aset-ti.perangkat-dc-drc.index')
            ->with('status', 'Perangkat DC/DRC berhasil ditambahkan.');
    }

    public function update(Request $request, DcDrcDevice $device): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $device->update($this->preparePayload($request, $device->id));

        return redirect()->route('admin.aset-ti.perangkat-dc-drc.index')
            ->with('status', 'Perangkat DC/DRC berhasil diperbarui.');
    }

    public function destroy(DcDrcDevice $device): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $device->delete();

        return redirect()->route('admin.aset-ti.perangkat-dc-drc.index')
            ->with('status', 'Perangkat DC/DRC berhasil dihapus.');
    }

    public function export()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $filename = 'perangkat-dc-drc-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new DcDrcDevicesExport(), $filename);
    }

    public function downloadTemplate()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return Excel::download(new DcDrcTemplateExport(), 'template-import-perangkat-dc-drc.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        try {
            Excel::import(new DcDrcDevicesImport(), $request->file('import_file'));
        } catch (ValidationException $e) {
            $failure = $e->failures()[0] ?? null;
            $message = $failure
                ? "Import gagal di baris {$failure->row()}: " . implode(', ', $failure->errors())
                : 'Import gagal. Pastikan format file sesuai template.';

            return redirect()->route('admin.aset-ti.perangkat-dc-drc.index')
                ->withErrors(['import_file' => $message]);
        }

        return redirect()->route('admin.aset-ti.perangkat-dc-drc.index')
            ->with('status', 'Import data perangkat DC/DRC berhasil.');
    }

    private function preparePayload(Request $request, ?int $currentDeviceId = null): array
    {
        $validated = $request->validate([
            'server_name' => ['required', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'max:255'],
            'host_server' => ['nullable', 'string', 'max:255'],
            'vm_host_id' => [
                'nullable',
                'integer',
                Rule::exists('dc_drc_devices', 'id')->where(function ($query) {
                    $query->whereRaw("LOWER(device_type) = 'vm host'");
                }),
            ],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'vlan' => ['nullable', 'string', 'max:255'],
            'nic_model' => ['nullable', 'string', 'max:255'],
            'os' => ['nullable', 'string', 'max:255'],
            'cpu_cores' => ['nullable', 'integer', 'min:1', 'max:2048'],
            'ram_gb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'storage_gb' => ['nullable', 'integer', 'min:1', 'max:10485760'],
            'objid_cpu' => ['nullable', 'string', 'max:255'],
            'objid_ram' => ['nullable', 'string', 'max:255'],
            'objid_ping' => ['nullable', 'string', 'max:255'],
            'objid_diskfree' => ['nullable', 'string', 'max:255'],
            'objid_traffic' => ['nullable', 'string', 'max:255'],
            'site' => ['nullable', 'string', 'max:255'],
            'system_role' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'max:255'],
            'owner_team' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['device_type'] = $this->normalizeDeviceType($validated['device_type'] ?? null);
        $deviceType = strtolower(trim((string) ($validated['device_type'] ?? '')));
        $vmHostId = isset($validated['vm_host_id']) ? (int) $validated['vm_host_id'] : null;

        if ($deviceType === 'vm') {
            if (!$vmHostId) {
                throw FormValidationException::withMessages([
                    'vm_host_id' => 'Untuk perangkat tipe VM, VM Server wajib dipilih dari perangkat bertipe vm host.',
                ]);
            }

            if ($currentDeviceId !== null && $vmHostId === $currentDeviceId) {
                throw FormValidationException::withMessages([
                    'vm_host_id' => 'Perangkat VM tidak boleh mereferensikan dirinya sendiri sebagai VM host.',
                ]);
            }

            $hostName = DcDrcDevice::query()->whereKey($vmHostId)->value('server_name');
            $validated['host_server'] = $hostName ?: null;
            $validated['vm_host_id'] = $vmHostId;
        } else {
            $validated['vm_host_id'] = null;
        }

        return $validated;
    }

    private function normalizeDeviceType(?string $deviceType): ?string
    {
        $rawValue = trim((string) $deviceType);
        if ($rawValue === '') {
            return null;
        }

        $compactValue = strtolower(preg_replace('/[\s_-]+/', '', $rawValue) ?? $rawValue);

        return match ($compactValue) {
            'vm' => 'VM',
            'vmhost' => 'vm host',
            'baremetal' => 'bare metal',
            'physical' => 'physical',
            default => $rawValue,
        };
    }
}
