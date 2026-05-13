<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CctvDevicesExport;
use App\Exports\CctvMissingUpdateExport;
use App\Exports\CctvTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\CctvDevicesImport;
use App\Models\CctvDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class CctvController extends Controller
{
    public function dashboard(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $withDvrQuery = CctvDevice::query()
            ->whereNotNull('dvr_brand')
            ->whereRaw("TRIM(dvr_brand) != ''")
            ->where('dvr_brand', '!=', '-');

        $noMonitorValues = ["''", "'-'", "'tidak ada'", "'tidakada'", "'none'", "'n/a'", "'na'", "'no monitor'"];
        $noMonitorListSql = implode(',', $noMonitorValues);

        $brandRows = CctvDevice::query()
            ->whereNotNull('dvr_brand')
            ->whereRaw("TRIM(dvr_brand) != ''")
            ->where('dvr_brand', '!=', '-')
            ->selectRaw('dvr_brand as label, COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $monitorStats = [
            'Ada Monitor' => (clone $withDvrQuery)
                ->whereNotNull('monitor')
                ->whereRaw("LOWER(TRIM(monitor)) NOT IN ({$noMonitorListSql})")
                ->count(),
            'Tidak Ada Monitor' => (clone $withDvrQuery)
                ->where(function ($query) use ($noMonitorListSql) {
                    $query->whereNull('monitor')
                        ->orWhereRaw("LOWER(TRIM(monitor)) IN ({$noMonitorListSql})");
                })
                ->count(),
        ];

        $connectionRows = (clone $withDvrQuery)
            ->selectRaw("CASE WHEN connection_status IS NULL OR TRIM(connection_status) = '' OR TRIM(connection_status) = '-' THEN 'Tidak Diketahui' ELSE connection_status END as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $harddiskRows = (clone $withDvrQuery)
            ->selectRaw("CASE WHEN harddisk IS NULL OR TRIM(harddisk) = '' OR TRIM(harddisk) = '-' THEN 'Tidak Diketahui' ELSE harddisk END as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $channelRows = (clone $withDvrQuery)
            ->selectRaw('channel_count, COUNT(*) as total')
            ->groupBy('channel_count')
            ->orderBy('channel_count')
            ->get();

        $channelLabels = $channelRows->map(function ($row) {
            return $row->channel_count ? ($row->channel_count . ' Channel') : 'Tidak Diketahui';
        })->values();
        $channelTotals = $channelRows->pluck('total')->values();

        $missingDvrRows = CctvDevice::query()
            ->select(['branch', 'office', 'monitor', 'connection_status', 'device_status', 'notes'])
            ->where(function ($query) {
                $query->whereNull('dvr_brand')
                    ->orWhereRaw("TRIM(dvr_brand) = ''")
                    ->orWhere('dvr_brand', '=', '-');
            })
            ->orderBy('branch')
            ->orderBy('office')
            ->paginate(6)
            ->withQueryString();

        return view('pages.dashboard.aset-ti.cctv-dashboard', [
            'brandLabels' => $brandRows->pluck('label')->values(),
            'brandTotals' => $brandRows->pluck('total')->values(),
            'monitorLabels' => array_keys($monitorStats),
            'monitorTotals' => array_values($monitorStats),
            'connectionLabels' => $connectionRows->pluck('label')->values(),
            'connectionTotals' => $connectionRows->pluck('total')->values(),
            'harddiskLabels' => $harddiskRows->pluck('label')->values(),
            'harddiskTotals' => $harddiskRows->pluck('total')->values(),
            'channelLabels' => $channelLabels,
            'channelTotals' => $channelTotals,
            'missingDvrRows' => $missingDvrRows,
        ]);
    }

    public function exportMissingUpdates()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $filename = 'daftar-kantor-belum-update-data-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new CctvMissingUpdateExport(), $filename);
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $allowedSorts = [
            'branch',
            'office',
            'dvr_brand',
            'channel_count',
            'harddisk',
            'monitor',
            'connection_status',
            'device_status',
            'notes',
            'created_at',
        ];

        $sortBy = request('sort_by', 'created_at');
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower((string) request('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $keyword = trim((string) request('keyword', ''));

        $devicesQuery = CctvDevice::query()
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($inner) use ($keyword) {
                    $inner->where('branch', 'like', '%' . $keyword . '%')
                        ->orWhere('office', 'like', '%' . $keyword . '%')
                        ->orWhere('dvr_brand', 'like', '%' . $keyword . '%')
                        ->orWhere('channel_count', 'like', '%' . $keyword . '%')
                        ->orWhere('harddisk', 'like', '%' . $keyword . '%')
                        ->orWhere('monitor', 'like', '%' . $keyword . '%')
                        ->orWhere('connection_status', 'like', '%' . $keyword . '%')
                        ->orWhere('device_status', 'like', '%' . $keyword . '%')
                        ->orWhere('notes', 'like', '%' . $keyword . '%');
                });
            })
            ->orderBy($sortBy, $sortDir);

        return view('pages.dashboard.aset-ti.cctv', [
            'devices' => $devicesQuery->paginate(10)->withQueryString(),
            'filters' => [
                'keyword' => $keyword,
            ],
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $this->validateRequest($request);
        $validated['dvr_photo_path'] = $request->file('dvr_photo')?->store('cctv', 'public');
        $validated['monitor_photo_path'] = $request->file('monitor_photo')?->store('cctv', 'public');

        CctvDevice::create($validated);

        return redirect()->route('admin.aset-ti.cctv.index')
            ->with('status', 'Perangkat CCTV berhasil ditambahkan.');
    }

    public function update(Request $request, CctvDevice $device): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $this->validateRequest($request);

        if ($request->hasFile('dvr_photo')) {
            if ($device->dvr_photo_path) {
                Storage::disk('public')->delete($device->dvr_photo_path);
            }
            $validated['dvr_photo_path'] = $request->file('dvr_photo')->store('cctv', 'public');
        }

        if ($request->hasFile('monitor_photo')) {
            if ($device->monitor_photo_path) {
                Storage::disk('public')->delete($device->monitor_photo_path);
            }
            $validated['monitor_photo_path'] = $request->file('monitor_photo')->store('cctv', 'public');
        }

        $device->update($validated);

        return redirect()->route('admin.aset-ti.cctv.index')
            ->with('status', 'Perangkat CCTV berhasil diperbarui.');
    }

    public function destroy(CctvDevice $device): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        if ($device->dvr_photo_path) {
            Storage::disk('public')->delete($device->dvr_photo_path);
        }
        if ($device->monitor_photo_path) {
            Storage::disk('public')->delete($device->monitor_photo_path);
        }

        $device->delete();

        return redirect()->route('admin.aset-ti.cctv.index')
            ->with('status', 'Perangkat CCTV berhasil dihapus.');
    }

    public function destroyAll(): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $devices = CctvDevice::query()
            ->select('dvr_photo_path', 'monitor_photo_path')
            ->get();

        foreach ($devices as $device) {
            if ($device->dvr_photo_path) {
                Storage::disk('public')->delete($device->dvr_photo_path);
            }
            if ($device->monitor_photo_path) {
                Storage::disk('public')->delete($device->monitor_photo_path);
            }
        }

        CctvDevice::query()->delete();

        return redirect()->route('admin.aset-ti.cctv.index')
            ->with('status', 'Semua data perangkat CCTV berhasil dihapus.');
    }

    public function export()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $filename = 'cctv-devices-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new CctvDevicesExport(), $filename);
    }

    public function downloadTemplate()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return Excel::download(new CctvTemplateExport(), 'template-import-cctv.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        try {
            Excel::import(new CctvDevicesImport(), $request->file('import_file'));
        } catch (ValidationException $e) {
            $failure = $e->failures()[0] ?? null;
            $message = $failure
                ? "Import gagal di baris {$failure->row()}: " . implode(', ', $failure->errors())
                : 'Import gagal. Pastikan format file sesuai template.';

            return redirect()->route('admin.aset-ti.cctv.index')
                ->withErrors(['import_file' => $message]);
        }

        return redirect()->route('admin.aset-ti.cctv.index')
            ->with('status', 'Import data CCTV berhasil.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'branch' => ['required', 'string', 'max:255'],
            'office' => ['required', 'string', 'max:255'],
            'dvr_brand' => ['required', 'string', 'max:255'],
            'channel_count' => ['nullable', 'integer', 'min:1', 'max:256'],
            'harddisk' => ['required', 'string', 'max:255'],
            'monitor' => ['required', 'string', 'max:255'],
            'connection_status' => ['nullable', 'string', 'max:255'],
            'device_status' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'dvr_photo' => ['nullable', 'image', 'max:4096'],
            'monitor_photo' => ['nullable', 'image', 'max:4096'],
        ]);
    }
}
