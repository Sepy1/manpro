@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="CR Eksternal Saya" />

    <div
        class="flex w-full max-w-none min-h-0 flex-1 flex-col gap-4"
        x-data="{
            updateRowChip (d) {
                if (! d || typeof d.id === 'undefined') { return; }

                var tr = document.querySelector('tr[data-cr-id=\'' + String(d.id) + '\']');
                var chip = tr ? tr.querySelector('.cr-status-chip') : null;
                if (chip && d.label) { chip.textContent = d.label; }
            }
        }"
        @extern-cr-row-status.window="updateRowChip($event.detail)"
    >
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <x-dashboard.accent-card accent-index="3" shell-overflow="visible" class="flex w-full min-h-0 flex-col" padding="p-5 lg:p-6">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daftar CR — PIC Vendor</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">CR yang ditugaskan kepada Anda dan sudah disetujui otorisator. Perbarui status Vendor Development, UAT, atau Go-Live.</p>
                </div>
                <form method="GET" action="{{ route('admin.cr-eksternal-vendor.index') }}" class="flex flex-wrap gap-2">
                    <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Cari nomor, nama CR, bidang, divisi..."
                        class="h-10 min-w-[200px] rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <button type="submit"
                        class="inline-flex h-10 items-center rounded-lg border border-brand-500 px-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                        Cari
                    </button>
                </form>
            </div>

            <div class="min-h-0 w-full overflow-x-auto">
                <table class="min-w-[860px] w-full border-separate border-spacing-0">
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
                            <tr
                                data-cr-id="{{ $row->id }}"
                                class="cursor-pointer border-b border-gray-100 transition-colors hover:bg-slate-50/80 dark:border-gray-800 dark:hover:bg-slate-900/60"
                                @click="$dispatch('open-extern-cr-detail', @js([
                                    'fragmentUrl' => route('admin.cr-eksternal-vendor.detail-modal', $row),
                                    'updateUrl' => route('admin.cr-eksternal-vendor.status', $row),
                                    'crId' => $row->id,
                                    'subtitle' => $row->nomor,
                                    'showEditLink' => false,
                                ]))"
                            >
                                <td class="whitespace-nowrap px-2 py-2 text-sm font-medium text-gray-900 dark:text-white/90">{{ $row->nomor }}</td>
                                <td class="max-w-[220px] truncate px-2 py-2 text-sm text-gray-700 dark:text-gray-300" title="{{ $row->nama }}">{{ $row->nama ?: '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $row->tanggal?->format('d/m/Y') }}</td>
                                <td class="max-w-[200px] truncate px-2 py-2 text-sm text-gray-700 dark:text-gray-300" title="{{ $row->division?->name }}">{{ $row->division?->name ?? '—' }}</td>
                                <td class="max-w-[200px] truncate px-2 py-2 text-sm text-gray-700 dark:text-gray-300" title="{{ $row->application?->name }}">{{ $row->application?->name ?? '—' }}</td>
                                <td class="px-2 py-2">
                                    <span class="cr-status-chip inline-flex max-w-[13rem] items-center rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-800 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-100">
                                        {{ $row->status->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-sm" @click.stop>
                                    <x-async-pdf-link
                                        href="{{ route('admin.cr-eksternal-vendor.print', $row) }}"
                                        title="Form permintaan perubahan PDF"
                                        class="inline-flex h-8 items-center rounded-lg border border-slate-500 px-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-500 dark:text-slate-200 dark:hover:bg-slate-800"
                                    >
                                        PDF
                                    </x-async-pdf-link>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-2 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada CR yang ditugaskan dan sudah disetujui otorisator.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->hasPages())
                <div class="mt-4">
                    {{ $items->links() }}
                </div>
            @endif
        </x-dashboard.accent-card>
    </div>
@endsection
