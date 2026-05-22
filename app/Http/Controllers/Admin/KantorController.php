<?php

namespace App\Http\Controllers\Admin;

use App\Exports\KantorExport;
use App\Exports\KantorTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\KantorsImport;
use App\Models\Kantor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as IlluminateValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class KantorController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('pages.dashboard.manajemen-kantor', [
            'kantors' => Kantor::query()
                ->with(['kasKantor' => fn ($q) => $q->orderBy('kode_kas')])
                ->orderBy('kode_kantor')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'kode_kantor' => ['required', 'string', 'max:50', 'unique:kantors,kode_kantor'],
            'nama_kantor' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Kantor::create([
            'kode_kantor' => trim($validated['kode_kantor']),
            'nama_kantor' => trim($validated['nama_kantor']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.manajemen-kantor.index')
            ->with('status', 'Kantor berhasil ditambahkan.');
    }

    public function update(Request $request, Kantor $kantor): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'kode_kantor' => ['required', 'string', 'max:50', 'unique:kantors,kode_kantor,'.$kantor->id],
            'nama_kantor' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $kantor->update([
            'kode_kantor' => trim($validated['kode_kantor']),
            'nama_kantor' => trim($validated['nama_kantor']),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.manajemen-kantor.index')
            ->with('status', 'Kantor berhasil diperbarui.');
    }

    public function destroy(Kantor $kantor): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $kantor->delete();

        return redirect()->route('admin.manajemen-kantor.index')
            ->with('status', 'Kantor berhasil dihapus.');
    }

    /**
     * Hapus seluruh cabang; kantor kas ikut (cascade). User dengan kantor_id mengacu ke cabang ini menjadi null (nullOnDelete).
     */
    public function destroyAll(): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        Kantor::query()->delete();

        return redirect()->route('admin.manajemen-kantor.index')
            ->with('status', 'Semua cabang dan kantor kas telah dihapus.');
    }

    public function export()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $filename = 'data-kantor-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new KantorExport, $filename);
    }

    public function downloadTemplate()
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return Excel::download(new KantorTemplateExport, 'template-import-kantor.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        try {
            Excel::import(new KantorsImport, $request->file('import_file'));
        } catch (IlluminateValidationException $e) {
            return redirect()->route('admin.manajemen-kantor.index')
                ->withErrors($e->errors());
        } catch (ExcelValidationException $e) {
            $failure = $e->failures()[0] ?? null;
            $message = $failure
                ? "Import gagal di baris {$failure->row()}: ".implode(', ', $failure->errors())
                : 'Import gagal. Pastikan format file sesuai template.';

            return redirect()->route('admin.manajemen-kantor.index')
                ->withErrors(['import_file' => $message]);
        }

        return redirect()->route('admin.manajemen-kantor.index')
            ->with('status', 'Import data kantor berhasil.');
    }
}
