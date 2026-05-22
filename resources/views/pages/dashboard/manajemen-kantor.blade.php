@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Manajemen Kantor" />

    <div
        class="flex w-full max-w-none min-h-0 flex-1 flex-col gap-4"
        x-data="{ showAddForm: false, showImportModal: @js($errors->has('import_file')), openCabangId: null, editingCabangId: null }"
    >
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('import_file'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ $errors->first('import_file') }}
            </div>
        @endif

        <x-dashboard.accent-card
            accent-index="2"
            shell-overflow="visible"
            class="flex w-full min-h-0 flex-col"
            padding="p-5 lg:p-6"
        >
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daftar Cabang / Kantor Induk</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Pola sama seperti VM host ke VM: klik baris cabang untuk membuka daftar kantor kas (KK).
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="showImportModal = true"
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-amber-500 px-4 text-sm font-medium text-amber-600 hover:bg-amber-50 dark:border-amber-400 dark:text-amber-300 dark:hover:bg-amber-900/20">
                        Import Excel
                    </button>
                    <a href="{{ route('admin.manajemen-kantor.export') }}" data-no-transition
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-blue-500 px-4 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:border-blue-400 dark:text-blue-300 dark:hover:bg-blue-900/20">
                        Export Excel
                    </a>
                    <form method="POST" action="{{ route('admin.manajemen-kantor.destroy-all') }}" class="inline"
                        onsubmit="return confirm('Hapus SEMUA cabang dan seluruh kantor kas? User yang terikat cabang akan kehilangan referensi kantor (kantor_id dikosongkan). Tindakan ini tidak bisa dibatalkan.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-red-500 px-4 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-400 dark:text-red-300 dark:hover:bg-red-900/20">
                            Hapus semua
                        </button>
                    </form>
                    <a href="{{ route('admin.manajemen-kantor.template') }}" data-no-transition
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-emerald-500 px-4 text-sm font-medium text-emerald-600 hover:bg-emerald-50 dark:border-emerald-400 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                        Unduh Template
                    </a>
                    <button type="button" @click="showAddForm = !showAddForm"
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Tambah Cabang
                    </button>
                </div>
            </div>

            <form x-show="showAddForm" x-cloak method="POST" action="{{ route('admin.manajemen-kantor.store') }}"
                class="mb-4 grid w-full grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 sm:grid-cols-2 lg:grid-cols-5 dark:border-gray-700">
                @csrf
                <input type="text" name="kode_kantor" value="{{ old('kode_kantor') }}" required placeholder="Kode cabang"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                <input type="text" name="nama_kantor" value="{{ old('nama_kantor') }}" required placeholder="Nama kantor / cabang"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90 lg:col-span-2" />
                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                        Cabang aktif
                    </label>
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                        Simpan
                    </button>
                </div>
            </form>

            <div class="min-h-0 w-full overflow-x-auto overflow-y-auto">
                <table class="w-full min-w-[920px] table-fixed border-separate border-spacing-0 text-left">
                    <thead class="[&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:border-b [&_th]:border-gray-200 [&_th]:bg-white dark:[&_th]:border-gray-800 dark:[&_th]:bg-slate-900">
                        <tr>
                            <th class="w-9 px-2 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                            <th class="w-[7%] whitespace-nowrap px-2 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">ID</th>
                            <th class="w-[12%] whitespace-nowrap px-2 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kode</th>
                            <th class="min-w-[220px] px-2 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cabang induk</th>
                            <th class="w-[12%] whitespace-nowrap px-2 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="w-[22%] whitespace-nowrap px-2 py-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kantors as $kantorRow)
                            @php $updateFormId = 'kantor-update-'.$kantorRow->id; @endphp
                            <tr
                                class="cursor-pointer border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]"
                                @click="if (editingCabangId !== {{ $kantorRow->id }}) { openCabangId = openCabangId === {{ $kantorRow->id }} ? null : {{ $kantorRow->id }} }">
                                <td class="px-2 py-3 text-center text-sm text-gray-400 dark:text-gray-500">
                                    <span class="inline-block transition-transform duration-150" :class="openCabangId === {{ $kantorRow->id }} ? 'translate-y-0 rotate-90' : ''">›</span>
                                </td>
                                <td class="relative whitespace-nowrap px-2 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <form id="{{ $updateFormId }}" method="POST" action="{{ route('admin.manajemen-kantor.update', $kantorRow) }}" class="sr-only" aria-hidden="true">
                                        @csrf
                                        @method('PUT')
                                    </form>
                                    {{ $kantorRow->id }}
                                </td>
                                <td class="truncate px-2 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span x-show="editingCabangId !== {{ $kantorRow->id }}" class="font-medium text-gray-900 dark:text-white/90">{{ $kantorRow->kode_kantor }}</span>
                                    <input type="text" name="kode_kantor" form="{{ $updateFormId }}" value="{{ $kantorRow->kode_kantor }}" required
                                        class="h-9 w-full min-w-[90px] max-w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                                        x-show="editingCabangId === {{ $kantorRow->id }}" x-cloak />
                                </td>
                                <td class="truncate px-2 py-3 text-xs text-gray-700 dark:text-gray-300">
                                    <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1" x-show="editingCabangId !== {{ $kantorRow->id }}">
                                        <span class="truncate font-medium text-gray-900 dark:text-white/90" title="{{ $kantorRow->nama_kantor }}">{{ $kantorRow->nama_kantor }}</span>
                                        <span class="shrink-0 text-[11px] text-brand-600 dark:text-brand-300">({{ $kantorRow->kasKantor->count() }} KK)</span>
                                    </div>
                                    <input type="text" name="nama_kantor" form="{{ $updateFormId }}" value="{{ $kantorRow->nama_kantor }}" required
                                        class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                                        x-show="editingCabangId === {{ $kantorRow->id }}" x-cloak />
                                </td>
                                <td class="whitespace-nowrap px-2 py-3 text-sm">
                                    <span x-show="editingCabangId !== {{ $kantorRow->id }}" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $kantorRow->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $kantorRow->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                    <label x-show="editingCabangId === {{ $kantorRow->id }}" x-cloak class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                        <input type="checkbox" name="is_active" form="{{ $updateFormId }}" value="1" @checked($kantorRow->is_active) class="rounded border-gray-300 dark:border-gray-600" />
                                        Aktif
                                    </label>
                                </td>
                                <td class="whitespace-nowrap px-2 py-3 text-xs" @click.stop>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="submit" form="{{ $updateFormId }}" x-show="editingCabangId === {{ $kantorRow->id }}" x-cloak
                                            class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-2 text-[11px] font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                            Simpan
                                        </button>
                                        <button
                                            type="button"
                                            @click.stop="
                                                if (editingCabangId === {{ $kantorRow->id }}) { editingCabangId = null }
                                                else { editingCabangId = {{ $kantorRow->id }}; openCabangId = null }
                                            "
                                            class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-2 text-[11px] font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                            <span x-text="editingCabangId === {{ $kantorRow->id }} ? 'Batal' : 'Edit'"></span>
                                        </button>
                                        <form method="POST" action="{{ route('admin.manajemen-kantor.delete', $kantorRow) }}" class="inline" onsubmit="return confirm('Hapus cabang ini beserta seluruh kantor kas di bawahnya?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-red-400 px-2 text-[11px] font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="openCabangId === {{ $kantorRow->id }}" x-transition.opacity x-cloak class="border-b border-gray-100 dark:border-gray-800">
                                <td colspan="6" class="cursor-default px-3 pb-3 pt-1" @click.stop>
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-white/[0.02]">
                                        <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                            Kantor kas di bawah cabang <span class="text-gray-900 dark:text-white/90">{{ $kantorRow->kode_kantor }}</span>
                                            · {{ $kantorRow->nama_kantor }}
                                        </h4>

                                    @if ($kantorRow->kasKantor->isEmpty())
                                            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Belum ada unit kantor kas. Tambahkan di bawah.</p>
                                    @else
                                            <div class="mb-3 space-y-2">
                                            @foreach ($kantorRow->kasKantor as $kas)
                                                <div class="content-card-tight px-3 py-2">
                                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                        <div class="min-w-0 flex-1">
                                                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ $kas->kode_kas }}@if ($kas->nama_kas)<span class="font-normal text-gray-500 dark:text-gray-400"> — {{ $kas->nama_kas }}</span>@endif</p>
                                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                                Status:
                                                                <span class="font-medium {{ $kas->is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400' }}">{{ $kas->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                            </p>
                                                        </div>
                                                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                                                            <form method="POST" action="{{ route('admin.manajemen-kantor.kas-kantor.update', [$kantorRow, $kas]) }}" class="flex flex-wrap items-center gap-2">
                                                                @csrf
                                                                @method('PUT')
                                                                <input type="text" name="kode_kas" value="{{ $kas->kode_kas }}" required title="Kode kas"
                                                                    class="h-8 w-24 rounded-lg border border-gray-300 bg-transparent px-2 py-1 text-xs dark:border-gray-700 dark:text-white/90" />
                                                                <input type="text" name="nama_kas" value="{{ $kas->nama_kas }}" placeholder="Nama" title="Nama KK"
                                                                    class="h-8 min-w-[140px] max-w-[220px] flex-1 rounded-lg border border-gray-300 bg-transparent px-2 py-1 text-xs dark:border-gray-700 dark:text-white/90" />
                                                                <label class="inline-flex items-center gap-1.5 whitespace-nowrap text-[11px] text-gray-600 dark:text-gray-300">
                                                                    <input type="checkbox" name="is_active" value="1" @checked($kas->is_active) class="rounded border-gray-300 dark:border-gray-600" />
                                                                    Aktif
                                                                </label>
                                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-2 text-[11px] font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                                                    Simpan
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="{{ route('admin.manajemen-kantor.kas-kantor.delete', [$kantorRow, $kas]) }}" class="inline" onsubmit="return confirm('Hapus kantor kas {{ $kas->kode_kas }}?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-red-400 px-2 text-[11px] font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20">
                                                                    Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('admin.manajemen-kantor.kas-kantor.store', $kantorRow) }}" class="flex flex-col gap-2 rounded-lg border border-dashed border-brand-400/70 bg-white/80 p-3 sm:flex-row sm:flex-wrap sm:items-end dark:border-brand-600/60 dark:bg-gray-900/40">
                                        @csrf
                                        <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-12 sm:gap-3">
                                            <div class="sm:col-span-3">
                                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Kode kas</label>
                                                <input type="text" name="kode_kas" value="{{ old('kode_kas') }}" required placeholder="Mis. KK-01"
                                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                                            </div>
                                            <div class="sm:col-span-7">
                                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Nama kantor kas</label>
                                                <input type="text" name="nama_kas" value="{{ old('nama_kas') }}" placeholder="Opsional"
                                                    class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                                            </div>
                                            <div class="flex items-center gap-2 sm:col-span-2">
                                                <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                                                    Aktif
                                                </label>
                                            </div>
                                        </div>
                                        <button type="submit" class="inline-flex h-9 shrink-0 items-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                                            Tambah kantor kas
                                        </button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data cabang.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-dashboard.accent-card>

        <div x-show="showImportModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showImportModal = false">
            <x-dashboard.accent-card accent-index="0" class="w-full max-w-lg overflow-hidden" padding="p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Import Data Kantor &amp; Kantor Kas (Excel)</h4>
                    <button type="button" @click="showImportModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form method="POST" action="{{ route('admin.manajemen-kantor.import') }}" enctype="multipart/form-data" class="flex flex-col gap-3">
                    @csrf
                    <input type="file" name="import_file" accept=".xlsx,.xls,.csv" required class="text-sm dark:text-gray-300" />
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm font-medium text-white hover:bg-amber-600">
                            Import Sekarang
                        </button>
                        <a href="{{ route('admin.manajemen-kantor.template') }}" data-no-transition class="inline-flex h-10 items-center rounded-lg border border-emerald-500 px-4 text-sm font-medium text-emerald-600 hover:bg-emerald-50 dark:border-emerald-400 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                            Unduh template
                        </a>
                    </div>
                </form>
            </x-dashboard.accent-card>
        </div>
    </div>
@endsection
