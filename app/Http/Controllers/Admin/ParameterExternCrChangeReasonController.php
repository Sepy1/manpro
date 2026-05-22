<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternCrChangeReason;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParameterExternCrChangeReasonController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.cr-eksternal.parameter-alasan', [
            'items' => ExternCrChangeReason::query()->orderBy('sort_order')->orderBy('name')->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:extern_cr_change_reasons,name'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ExternCrChangeReason::create([
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.parameter.cr-alasan-perubahan.index')
            ->with('status', 'Alasan perubahan ditambahkan.');
    }

    public function update(Request $request, ExternCrChangeReason $changeReason): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:extern_cr_change_reasons,name,'.$changeReason->id],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $changeReason->update([
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.parameter.cr-alasan-perubahan.index')
            ->with('status', 'Alasan perubahan diperbarui.');
    }

    public function destroy(ExternCrChangeReason $changeReason): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        try {
            $changeReason->delete();
        } catch (QueryException) {
            return redirect()->route('admin.parameter.cr-alasan-perubahan.index')
                ->withErrors(['delete' => 'Tidak dapat dihapus: masih dipakai oleh CR Eksternal.']);
        }

        return redirect()->route('admin.parameter.cr-alasan-perubahan.index')
            ->with('status', 'Alasan perubahan dihapus.');
    }
}
