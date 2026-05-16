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
    public function dashboard(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

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

        return view('pages.dashboard.aset-ti.perangkat-dc-drc-dashboard', [
            'hostTypeLabels' => array_keys($hostTypeStats),
            'hostTypeTotals' => array_values($hostTypeStats),
            'brandLabels' => $brandRows->pluck('label')->values(),
            'brandTotals' => $brandRows->pluck('total')->values(),
            'vmPerHostLabels' => $vmPerHostRows->pluck('label')->values(),
            'vmPerHostTotals' => $vmPerHostRows->pluck('total')->values(),
        ]);
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
            'site' => ['nullable', 'string', 'max:255'],
            'system_role' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'max:255'],
            'owner_team' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

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
}
