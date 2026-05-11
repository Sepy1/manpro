<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DivisionController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.manajemen-divisi', [
            'divisions' => Division::query()->orderBy('name')->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:divisions,name'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Division::create([
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.manajemen-divisi.index')
            ->with('status', 'Divisi berhasil ditambahkan.');
    }

    public function update(Request $request, Division $division): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:divisions,name,' . $division->id],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $division->update([
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.manajemen-divisi.index')
            ->with('status', 'Divisi berhasil diperbarui.');
    }

    public function destroy(Division $division): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $division->delete();

        return redirect()->route('admin.manajemen-divisi.index')
            ->with('status', 'Divisi berhasil dihapus.');
    }
}

