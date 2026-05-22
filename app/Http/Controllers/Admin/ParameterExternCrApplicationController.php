<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternCrApplication;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParameterExternCrApplicationController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.cr-eksternal.parameter-aplikasi', [
            'items' => ExternCrApplication::query()->orderBy('sort_order')->orderBy('name')->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:extern_cr_applications,name'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ExternCrApplication::create([
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.parameter.cr-aplikasi.index')
            ->with('status', 'Aplikasi ditambahkan.');
    }

    public function update(Request $request, ExternCrApplication $application): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:extern_cr_applications,name,'.$application->id],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $application->update([
            'name' => $validated['name'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.parameter.cr-aplikasi.index')
            ->with('status', 'Aplikasi diperbarui.');
    }

    public function destroy(ExternCrApplication $application): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        try {
            $application->delete();
        } catch (QueryException) {
            return redirect()->route('admin.parameter.cr-aplikasi.index')
                ->withErrors(['delete' => 'Tidak dapat dihapus: masih dipakai oleh CR Eksternal.']);
        }

        return redirect()->route('admin.parameter.cr-aplikasi.index')
            ->with('status', 'Aplikasi dihapus.');
    }
}
