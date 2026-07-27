<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternCrApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExternCrApiKeyController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.manajemen-api-key', [
            'apiKeys' => ExternCrApiKey::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:extern_cr_api_keys,name'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $plain = Str::random(32);

        ExternCrApiKey::query()->create([
            'name' => $validated['name'],
            'key_hash' => hash('sha256', $plain),
            'key_prefix' => substr($plain, 0, 8),
            'is_active' => true,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return redirect()->route('admin.manajemen-api-key.index')
            ->with('status', 'API key berhasil dibuat. Simpan nilai key di bawah ini: '.$plain);
    }

    public function update(Request $request, ExternCrApiKey $externCrApiKey): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:extern_cr_api_keys,name,'.$externCrApiKey->id],
            'is_active' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $externCrApiKey->update([
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return redirect()->route('admin.manajemen-api-key.index')
            ->with('status', 'API key berhasil diperbarui.');
    }

    public function destroy(ExternCrApiKey $externCrApiKey): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $externCrApiKey->delete();

        return redirect()->route('admin.manajemen-api-key.index')
            ->with('status', 'API key berhasil dihapus.');
    }
}
