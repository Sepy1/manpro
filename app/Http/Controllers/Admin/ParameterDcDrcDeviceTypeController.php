<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DcDrcDevice;
use App\Models\DcDrcDeviceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParameterDcDrcDeviceTypeController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.parameter.dc-drc-device-types', [
            'items' => DcDrcDeviceType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $this->validatePayload($request);
        DcDrcDeviceType::query()->create($validated);

        return redirect()->route('admin.parameter.dc-drc-device-types.index')
            ->with('status', 'Tipe perangkat DC/DRC berhasil ditambahkan.');
    }

    public function update(Request $request, DcDrcDeviceType $deviceType): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $this->validatePayload($request, $deviceType);
        $oldName = $deviceType->name;

        DB::transaction(function () use ($deviceType, $validated, $oldName): void {
            $deviceType->update($validated);

            if ($oldName !== $validated['name']) {
                DcDrcDevice::query()
                    ->whereRaw('LOWER(device_type) = ?', [mb_strtolower($oldName)])
                    ->update(['device_type' => $validated['name']]);
            }
        });

        return redirect()->route('admin.parameter.dc-drc-device-types.index')
            ->with('status', 'Tipe perangkat DC/DRC berhasil diperbarui.');
    }

    public function destroy(DcDrcDeviceType $deviceType): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $isUsed = DcDrcDevice::query()
            ->whereRaw('LOWER(device_type) = ?', [mb_strtolower($deviceType->name)])
            ->exists();

        if ($isUsed) {
            return redirect()->route('admin.parameter.dc-drc-device-types.index')
                ->withErrors(['delete' => 'Tipe tidak dapat dihapus karena masih digunakan oleh perangkat DC/DRC. Nonaktifkan tipe jika tidak ingin menampilkannya pada form.']);
        }

        $deviceType->delete();

        return redirect()->route('admin.parameter.dc-drc-device-types.index')
            ->with('status', 'Tipe perangkat DC/DRC berhasil dihapus.');
    }

    /** @return array{name: string, sort_order: int, is_active: bool} */
    private function validatePayload(Request $request, ?DcDrcDeviceType $deviceType = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('dc_drc_device_types', 'name')->ignore($deviceType?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
