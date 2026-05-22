<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kantor;
use App\Models\KasKantor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KasKantorController extends Controller
{
    public function store(Request $request, Kantor $kantor): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'kode_kas' => [
                'required',
                'string',
                'max:80',
                Rule::unique('kas_kantor', 'kode_kas')->where(fn ($q) => $q->where('kantor_id', $kantor->id)),
            ],
            'nama_kas' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $kantor->kasKantor()->create([
            'kode_kas' => trim($validated['kode_kas']),
            'nama_kas' => isset($validated['nama_kas']) ? trim((string) $validated['nama_kas']) ?: null : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.manajemen-kantor.index')
            ->with('status', 'Kantor kas berhasil ditambahkan.');
    }

    public function update(Request $request, Kantor $kantor, KasKantor $kasKantor): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        abort_unless((int) $kasKantor->kantor_id === (int) $kantor->id, 404);

        $validated = $request->validate([
            'kode_kas' => [
                'required',
                'string',
                'max:80',
                Rule::unique('kas_kantor', 'kode_kas')
                    ->where(fn ($q) => $q->where('kantor_id', $kantor->id))
                    ->ignore($kasKantor->id),
            ],
            'nama_kas' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $kasKantor->update([
            'kode_kas' => trim($validated['kode_kas']),
            'nama_kas' => isset($validated['nama_kas']) ? trim((string) $validated['nama_kas']) ?: null : null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.manajemen-kantor.index')
            ->with('status', 'Kantor kas berhasil diperbarui.');
    }

    public function destroy(Kantor $kantor, KasKantor $kasKantor): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        abort_unless((int) $kasKantor->kantor_id === (int) $kantor->id, 404);

        $kasKantor->delete();

        return redirect()->route('admin.manajemen-kantor.index')
            ->with('status', 'Kantor kas berhasil dihapus.');
    }
}
