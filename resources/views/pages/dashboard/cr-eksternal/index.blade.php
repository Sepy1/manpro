@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="CR Eksternal" />

        <div
            class="flex h-full w-full max-w-none min-h-0 flex-1 flex-col gap-4"
            x-data="{
                badgeBaseClass: @js(\App\Enums\ExternCrStatus::listBadgeShellClasses()),
                badgeClassMap: @js(\App\Enums\ExternCrStatus::listBadgeClassMap()),
                updateRowChip (d) {
                    if (! d || typeof d.id === 'undefined') { return; }

                    var tr = document.querySelector('tr[data-cr-id=\'' + String(d.id) + '\']');

                    var chip = tr ? tr.querySelector('.cr-status-chip') : null;
                    if (chip && d.label) { chip.textContent = d.label; }
                    if (chip && d.status && this.badgeClassMap[d.status]) {
                        chip.className = this.badgeBaseClass + ' ' + this.badgeClassMap[d.status];
                    }
                }
            }"
            @extern-cr-row-status.window="updateRowChip($event.detail)"
        >
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif
        @if (session('flash_error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/45 dark:bg-red-950/30 dark:text-red-200">
                {{ session('flash_error') }}
            </div>
        @endif

        <x-dashboard.accent-card accent-index="3" shell-overflow="visible" class="flex h-full w-full min-h-0 flex-1 flex-col" padding="p-5 lg:p-6">
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

            <div class="min-h-0 flex-1 w-full overflow-auto">
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
                            <tr
                                data-cr-id="{{ $row->id }}"
                                class="cursor-pointer border-b border-gray-100 transition-colors hover:bg-slate-50/80 dark:border-gray-800 dark:hover:bg-slate-900/60"
                                @click="$dispatch('open-extern-cr-detail', @js([
                                    'fragmentUrl' => route('admin.cr-eksternal.detail-modal', $row),
                                    'updateUrl' => route('admin.cr-eksternal.status', $row),
                                    'crId' => $row->id,
                                    'subtitle' => $row->nomor,
                                ]))"
                            >
                                <td class="whitespace-nowrap px-2 py-2 text-sm font-medium text-gray-900 dark:text-white/90">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span>{{ $row->nomor }}</span>
                                        @if ($row->wa_authorization_decision === \App\Models\ExternCr::WA_AUTH_APPROVED)
                                            <span class="inline-flex items-center rounded-md border border-emerald-300 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200" title="Otorisasi WhatsApp: disetujui">
                                                Disetujui
                                            </span>
                                        @elseif ($row->wa_authorization_decision === \App\Models\ExternCr::WA_AUTH_REJECTED)
                                            <span class="inline-flex items-center rounded-md border border-rose-300 bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-800 dark:border-rose-700 dark:bg-rose-950/40 dark:text-rose-200" title="Otorisasi WhatsApp: ditolak">
                                                Ditolak
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="max-w-[220px] truncate px-2 py-2 text-sm text-gray-700 dark:text-gray-300" title="{{ $row->nama }}">{{ $row->nama ? $row->nama : '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $row->tanggal?->format('d/m/Y') }}</td>
                                <td class="max-w-[200px] truncate px-2 py-2 text-sm text-gray-700 dark:text-gray-300" title="{{ $row->division?->name }}">{{ $row->division?->name ?? '-' }}</td>
                                <td class="max-w-[200px] truncate px-2 py-2 text-sm text-gray-700 dark:text-gray-300" title="{{ $row->application?->name }}">{{ $row->application?->name ?? '-' }}</td>
                                <td class="px-2 py-2">
                                    <span class="{{ \App\Enums\ExternCrStatus::listBadgeShellClasses() }} {{ $row->status->listBadgeClasses() }}" title="Klik baris untuk detail &amp; ubah status">
                                        {{ $row->status->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-2 text-sm" @click.stop>
                                    <div class="flex flex-wrap gap-2">
                                        <x-async-pdf-link
                                            href="{{ route('admin.cr-eksternal.print', $row) }}"
                                            title="Form permintaan perubahan PDF"
                                            class="inline-flex h-8 items-center rounded-lg border border-slate-500 px-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-500 dark:text-slate-200 dark:hover:bg-slate-800"
                                        >
                                            PDF
                                        </x-async-pdf-link>
                                        <button type="button" title="Riwayat perubahan"
                                            class="inline-flex h-8 items-center rounded-lg border border-slate-400 px-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-500 dark:text-slate-200 dark:hover:bg-slate-800"
                                            @click.prevent="$dispatch('open-extern-cr-history', @js([
                                                'fragmentUrl' => route('admin.cr-eksternal.history-modal', $row),
                                                'subtitle' => $row->nomor,
                                                'namaLabel' => $row->nama,
                                            ]))">
                                            Riwayat
                                        </button>
                                        @if ($row->hasWaAuthorizationDecision())
                                            <button
                                                type="button"
                                                title="Reset keputusan lalu kirim undangan otorisasi WhatsApp baru"
                                                class="inline-flex h-8 items-center rounded-lg border border-amber-500 px-2 text-xs font-medium text-amber-800 hover:bg-amber-50 dark:border-amber-400 dark:text-amber-200 dark:hover:bg-amber-950/40"
                                                @click.prevent="$dispatch('open-extern-cr-send-auth', @js([
                                                    'authorizersJsonUrl' => route('admin.cr-eksternal.authorizers-data', $row).'?reauthorize=1',
                                                    'sendUrl' => route('admin.cr-eksternal.send-wa-authorization', $row),
                                                    'crNomor' => $row->nomor,
                                                    'reauthorize' => true,
                                                ]))"
                                            >
                                                Otorisasi ulang
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                title="Pilih otorisator lalu kirim template WhatsApp"
                                                class="inline-flex h-8 items-center rounded-lg border border-teal-500 px-2 text-xs font-medium text-teal-700 hover:bg-teal-50 dark:border-teal-400 dark:text-teal-200 dark:hover:bg-teal-950/40"
                                                @click.prevent="$dispatch('open-extern-cr-send-auth', @js([
                                                    'authorizersJsonUrl' => route('admin.cr-eksternal.authorizers-data', $row),
                                                    'sendUrl' => route('admin.cr-eksternal.send-wa-authorization', $row),
                                                    'crNomor' => $row->nomor,
                                                ]))"
                                            >
                                                Kirim Otorisasi
                                            </button>
                                        @endif
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
