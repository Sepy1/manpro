@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="CR Eksternal" />

    <div class="flex w-full max-w-none min-h-0 flex-1 flex-col gap-4">
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <x-dashboard.accent-card accent-index="3" shell-overflow="visible" class="flex w-full min-h-0 flex-col" padding="p-5 lg:p-6">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daftar Change Request Eksternal</h3>
                <div class="flex flex-wrap gap-2">
                    <form method="GET" action="{{ route('admin.cr-eksternal.index') }}" class="flex flex-wrap gap-2">
                        <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Cari nomor, nama CR, bidang, divisi..."
                            class="h-10 min-w-[200px] rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                        <button type="submit"
                            class="inline-flex h-10 items-center rounded-lg border border-brand-500 px-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                            Cari
                        </button>
                    </form>
                    <a href="{{ route('admin.cr-eksternal.create') }}" data-no-transition
                        class="inline-flex h-10 items-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-md shadow-sky-900/25 ring-2 ring-sky-500/80 transition-colors hover:bg-sky-700 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800 dark:bg-sky-500 dark:text-white dark:ring-sky-400/70 dark:hover:bg-sky-400">
                        Tambah CR
                    </a>
                </div>
            </div>

            <div class="min-h-0 w-full overflow-x-auto">
                <table class="min-w-[960px] w-full border-separate border-spacing-0">
                    <thead class="[&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:border-b [&_th]:border-gray-200 [&_th]:bg-white dark:[&_th]:border-gray-800 dark:[&_th]:bg-slate-900">
                        <tr>
                            <th class="px-2 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nomor</th>
                            <th class="px-2 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                            <th class="px-2 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tanggal</th>
                            <th class="px-2 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Divisi pemohon</th>
                            <th class="px-2 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sistem</th>
                            <th class="px-2 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-2 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="whitespace-nowrap px-2 py-2 text-sm font-medium text-gray-900 dark:text-white/90">{{ $row->nomor }}</td>
                                <td class="max-w-[220px] truncate px-2 py-2 text-sm text-gray-700 dark:text-gray-300" title="{{ $row->nama }}">{{ $row->nama ? $row->nama : '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $row->tanggal?->format('d/m/Y') }}</td>
                                <td class="max-w-[200px] truncate px-2 py-2 text-sm text-gray-700 dark:text-gray-300" title="{{ $row->division?->name }}">{{ $row->division?->name ?? '-' }}</td>
                                <td class="max-w-[200px] truncate px-2 py-2 text-sm text-gray-700 dark:text-gray-300" title="{{ $row->application?->name }}">{{ $row->application?->name ?? '-' }}</td>
                                <td class="px-2 py-2">
                                    <form method="POST" action="{{ route('admin.cr-eksternal.status', $row) }}" class="min-w-[9.5rem]">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" autocomplete="off" title="Status CR"
                                            class="h-9 w-full max-w-[12rem] rounded-lg border border-gray-300 bg-transparent px-2 py-1 text-xs font-medium dark:border-gray-600 dark:text-white/90"
                                            onchange="(this.form.requestSubmit || this.form.submit).call(this.form)">
                                            @foreach (\App\Enums\ExternCrStatus::cases() as $case)
                                                <option value="{{ $case->value }}" @selected($row->status->value === $case->value)>{{ $case->label() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-sm">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('admin.cr-eksternal.print', $row) }}" target="_blank" rel="noopener noreferrer" data-no-transition
                                            title="Form permintaan perubahan PDF"
                                            class="inline-flex h-8 items-center rounded-lg border border-slate-500 px-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-500 dark:text-slate-200 dark:hover:bg-slate-800">
                                            PDF
                                        </a>
                                        <a href="{{ route('admin.cr-eksternal.edit', $row) }}" data-no-transition
                                            class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('admin.cr-eksternal.delete', $row) }}" class="inline" onsubmit="return confirm('Hapus CR {{ $row->nomor }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex h-8 items-center rounded-lg border border-red-400 px-2 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada data. Tambah dari tombol atas atau isi master <strong>Aplikasi</strong> dan <strong>Alasan perubahan</strong> di menu Parameter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($items->hasPages())
                <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-800">
                    {{ $items->links() }}
                </div>
            @endif
        </x-dashboard.accent-card>
    </div>
@endsection
