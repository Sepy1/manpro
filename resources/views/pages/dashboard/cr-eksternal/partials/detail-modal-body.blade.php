@php
    /** @var \App\Models\ExternCr $cr */
    $line = fn (?string $s) => $s !== null && trim($s) !== '' ? nl2br(e(trim($s))) : '—';
    $lbl = 'text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
    $textBoxScroll = 'mt-1.5 rounded-lg border border-slate-200/90 bg-white/80 px-3 py-2.5 text-sm leading-relaxed text-slate-800 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 max-h-[10.5rem] overflow-y-auto [scrollbar-gutter:stable]';
@endphp

<div class="space-y-6 text-left">
    {{-- Ringkasan --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white dark:border-slate-600 dark:from-slate-900/80 dark:to-slate-900">
        <div class="border-b border-slate-200/80 px-4 py-2.5 dark:border-slate-700">
            <p class="{{ $lbl }} mb-1">Nomor dokumen</p>
            <p class="font-mono text-sm font-bold tracking-tight text-slate-900 dark:text-white">{{ $cr->nomor }}</p>
        </div>
        <div class="px-4 py-3">
            <p class="{{ $lbl }}">Nama CR</p>
            <p class="mt-1 text-base font-semibold leading-snug text-slate-900 dark:text-white">{{ $cr->nama ?: '—' }}</p>
        </div>
    </section>

    {{-- Status --}}
    <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-600">
        <p class="{{ $lbl }}">Status saat ini</p>
        <div class="mt-2 flex flex-wrap items-start gap-2">
            <span
                id="extern-cr-detail-status-chip"
                data-current-chip
                class="inline-flex items-center rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-800 dark:border-slate-500 dark:bg-slate-800 dark:text-white"
            >
                {{ $cr->status->label() }}
            </span>
        </div>
        @if (! empty($latestStatusChangeNote))
            <div class="mt-3 rounded-lg border border-violet-200/90 bg-violet-50/80 px-3 py-2.5 dark:border-violet-800/50 dark:bg-violet-950/40">
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">Catatan pergantian status</p>
                <div class="mt-2 max-h-36 overflow-y-auto whitespace-pre-wrap text-sm leading-relaxed text-slate-800 dark:text-slate-200">{{ $latestStatusChangeNote }}</div>
            </div>
        @endif
    </section>

    {{-- Metadata singkat --}}
    <section aria-label="Metadata">
        <p class="{{ $lbl }} mb-3">Informasi klasifikasi</p>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/40">
                <dt class="{{ $lbl }}">Sistem / aplikasi</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $cr->application?->name ?? '—' }}</dd>
            </div>
            <div class="rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/40">
                <dt class="{{ $lbl }}">Alasan perubahan</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $cr->changeReason?->name ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    {{-- Narasi permintaan --}}
    <section aria-label="Rincian permintaan" class="space-y-4">
        <div class="flex items-center gap-2">
            <span class="{{ $lbl }} mb-0">Rincian permintaan</span>
            <span class="hidden h-px flex-1 bg-slate-200 dark:bg-slate-700 sm:block" aria-hidden="true"></span>
        </div>

        <div>
            <p class="{{ $lbl }}">Kondisi saat ini</p>
            <div class="{{ $textBoxScroll }}">{!! $line($cr->kondisi_saat_ini) !!}</div>
        </div>
        <div>
            <p class="{{ $lbl }}">Perubahan yang diharapkan</p>
            <div class="{{ $textBoxScroll }}">{!! $line($cr->perubahan_diharapkan) !!}</div>
        </div>
        <div>
            <p class="{{ $lbl }}">Risiko terkait</p>
            <div class="{{ $textBoxScroll }}">{!! $line($cr->risiko_bila_tidak) !!}</div>
        </div>
        <div>
            <p class="{{ $lbl }}">Deskripsi permintaan</p>
            <div class="{{ $textBoxScroll }}">{!! $line($cr->deskripsi_permintaan) !!}</div>
        </div>
    </section>

    {{-- Lampiran --}}
    <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-600">
        <p class="{{ $lbl }} mb-3">Lampiran</p>
        @if ($cr->attachments->isEmpty())
            <p class="text-sm italic text-slate-500 dark:text-slate-400">Tidak ada lampiran.</p>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-700" role="list">
                @foreach ($cr->attachments as $att)
                    <li class="py-2 first:pt-0 last:pb-0">
                        <a
                            href="{{ route('admin.cr-eksternal.attachments.download', [$cr, $att]) }}"
                            data-no-transition
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 underline decoration-brand-500/70 underline-offset-2 hover:text-brand-800 dark:text-brand-400 dark:hover:text-brand-300"
                        >
                            <svg class="h-4 w-4 shrink-0 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25V16.5M16.5 12L12 16.5 7.5 12m4.5 4.5V3"/>
                            </svg>
                            <span>{{ $att->original_name ?? basename((string) $att->path) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Ubah status (form – tombol utama di footer modal) --}}
    <section class="rounded-xl border border-dashed border-sky-300/70 bg-sky-50/40 p-4 dark:border-sky-800/60 dark:bg-sky-950/25">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Perbarui status CR</p>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Pilih status lalu tulis catatan bila perlu; simpan pakai tombol di bawah jendela.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 sm:items-end">
            <div class="sm:col-span-1">
                <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400" for="extern-cr-detail-status-select">Status</label>
                <select
                    id="extern-cr-detail-status-select"
                    class="h-11 w-full max-w-none rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                    autocomplete="off"
                >
                    @foreach (\App\Enums\ExternCrStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($cr->status->value === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400" for="extern-cr-detail-status-note">Catatan (opsional)</label>
                <textarea
                    id="extern-cr-detail-status-note"
                    rows="3"
                    placeholder="Mis. alasan penyimpangan SLA, PIC, atau kelengkapan dokumen…"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"
                ></textarea>
            </div>
        </div>
    </section>
</div>
