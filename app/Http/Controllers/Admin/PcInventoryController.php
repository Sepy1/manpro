<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kantor;
use App\Models\PcInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PcInventoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'kantor_id' => ['nullable', 'integer', 'exists:kantors,id'],
            'kas_kantor_id' => ['nullable', 'integer', 'exists:kas_kantor,id'],
            'antivirus_status' => ['nullable', 'string', 'max:50'],
            'status_perangkat' => ['nullable', Rule::in(['Inventaris', 'Device Pribadi (BYOD)'])],
            'seen_from' => ['nullable', 'date'],
            'seen_until' => ['nullable', 'date', 'after_or_equal:seen_from'],
            'sort' => ['nullable', Rule::in(['last_seen_at', 'hostname', 'nama_user', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $sort = $validated['sort'] ?? 'last_seen_at';
        $direction = $validated['direction'] ?? 'desc';

        $query = PcInventory::query()
            ->with(['kantor:id,kode_kantor,nama_kantor', 'kasKantor:id,kantor_id,kode_kas,nama_kas'])
            ->when($keyword !== '', function ($builder) use ($keyword): void {
                $builder->where(function ($nested) use ($keyword): void {
                    $nested->where('hostname', 'like', "%{$keyword}%")
                        ->orWhere('nama_user', 'like', "%{$keyword}%")
                        ->orWhere('serial_number', 'like', "%{$keyword}%")
                        ->orWhere('machine_guid', 'like', "%{$keyword}%")
                        ->orWhere('mac_address', 'like', "%{$keyword}%")
                        ->orWhere('ip_address', 'like', "%{$keyword}%")
                        ->orWhere('cpu_name', 'like', "%{$keyword}%")
                        ->orWhere('os_info', 'like', "%{$keyword}%");
                });
            })
            ->when(! empty($validated['kantor_id']), fn ($builder) => $builder->where('kantor_id', $validated['kantor_id']))
            ->when(! empty($validated['kas_kantor_id']), fn ($builder) => $builder->where('kas_kantor_id', $validated['kas_kantor_id']))
            ->when(! empty($validated['antivirus_status']), fn ($builder) => $builder->where('antivirus_status', $validated['antivirus_status']))
            ->when(! empty($validated['status_perangkat']), fn ($builder) => $builder->where('status_perangkat', $validated['status_perangkat']))
            ->when(! empty($validated['seen_from']), fn ($builder) => $builder->whereDate('last_seen_at', '>=', $validated['seen_from']))
            ->when(! empty($validated['seen_until']), fn ($builder) => $builder->whereDate('last_seen_at', '<=', $validated['seen_until']));

        $filteredCount = (clone $query)->count();

        return view('pages.dashboard.aset-ti.pc-inventories', [
            'inventories' => $query->orderBy($sort, $direction)->paginate(25)->withQueryString(),
            'kantors' => Kantor::query()
                ->with(['kasKantor' => fn ($builder) => $builder->orderBy('kode_kas')])
                ->orderBy('kode_kantor')
                ->get(['id', 'kode_kantor', 'nama_kantor']),
            'antivirusStatuses' => PcInventory::query()
                ->whereNotNull('antivirus_status')
                ->distinct()
                ->orderBy('antivirus_status')
                ->pluck('antivirus_status'),
            'filters' => [
                'keyword' => $keyword,
                'kantor_id' => (string) ($validated['kantor_id'] ?? ''),
                'kas_kantor_id' => (string) ($validated['kas_kantor_id'] ?? ''),
                'antivirus_status' => (string) ($validated['antivirus_status'] ?? ''),
                'status_perangkat' => (string) ($validated['status_perangkat'] ?? ''),
                'seen_from' => (string) ($validated['seen_from'] ?? ''),
                'seen_until' => (string) ($validated['seen_until'] ?? ''),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'filteredCount' => $filteredCount,
            'totalCount' => PcInventory::query()->count(),
            'editInventory' => $request->integer('edit')
                ? PcInventory::query()->findOrFail($request->integer('edit'))
                : null,
        ]);
    }

    public function update(Request $request, PcInventory $inventory): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'nama_user' => ['required', 'string', 'max:255'],
            'jabatan' => ['required', Rule::in([
                'Kepala Divisi', 'Kepala Cabang', 'Kepala Kantor Kas', 'Kepala Bidang',
                'Kepala Sub Bidang', 'Staf / AO / Admin', 'Teller', 'Customer Service',
            ])],
            'status_perangkat' => ['required', Rule::in(['Inventaris', 'Device Pribadi (BYOD)'])],
            'kantor_id' => ['required', 'integer', Rule::exists('kantors', 'id')->where('is_active', true)],
            'kas_kantor_id' => [
                'nullable',
                'integer',
                Rule::exists('kas_kantor', 'id')->where(fn ($query) => $query
                    ->where('kantor_id', $request->integer('kantor_id'))
                    ->where('is_active', true)),
            ],
        ]);

        $kantor = Kantor::query()->findOrFail($validated['kantor_id']);
        $kasKantor = isset($validated['kas_kantor_id'])
            ? $kantor->kasKantor()->findOrFail($validated['kas_kantor_id'])
            : null;

        $inventory->update([
            ...$validated,
            'kode_kantor' => $kantor->kode_kantor,
            'kode_kas' => $kasKantor?->kode_kas,
        ]);

        return redirect()->route('admin.aset-ti.end-point.index')
            ->with('status', 'Data end point berhasil diperbarui.');
    }

    public function destroy(PcInventory $inventory): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $inventory->delete();

        return redirect()->route('admin.aset-ti.end-point.index')
            ->with('status', 'Data end point berhasil dihapus.');
    }
}
