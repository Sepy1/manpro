<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.manajemen-vendor', [
            'vendors' => Vendor::query()->orderBy('name')->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:vendors,name'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Vendor::create([
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.manajemen-vendor.index')
            ->with('status', 'Vendor berhasil ditambahkan.');
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:vendors,name,' . $vendor->id],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $vendor->update([
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.manajemen-vendor.index')
            ->with('status', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $vendor->delete();

        return redirect()->route('admin.manajemen-vendor.index')
            ->with('status', 'Vendor berhasil dihapus.');
    }
}

