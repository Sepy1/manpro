@extends('layouts.admin')

@section('admin-content')
    @php
        /** @var \App\Models\ExternCr|null $externCr */
        $edit = $externCr !== null;
        $action = $edit ? route('admin.cr-eksternal.update', $externCr) : route('admin.cr-eksternal.store');

        $involvedTerlibatDefault = '';
        if ($edit) {
            $saved = trim((string) ($externCr->divisions_terlibat_text ?? ''));
            if ($saved !== '') {
                $involvedTerlibatDefault = $saved;
            } elseif ($externCr->divisionsInvolved->isNotEmpty()) {
                $involvedTerlibatDefault = $externCr->divisionsInvolved
                    ->sortBy('name')
                    ->pluck('name')
                    ->implode(', ');
            }
        }
        $slotLampiran = $edit ? max(0, 5 - $externCr->attachments->count()) : 5;
        $terlibatDivisionNames = isset($divisionHints) ? $divisionHints->values()->all() : [];
    @endphp

    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden">
        <div class="shrink-0">
            <x-common.page-breadcrumb pageTitle="{{ $edit ? 'Edit '.$externCr->nomor : 'Tambah CR Eksternal' }}" />
        </div>

        @if ($errors->any())
            <div class="shrink-0 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <x-dashboard.accent-card accent-index="3" shell-overflow="hidden" padding="p-0" class="flex min-h-0 flex-1 flex-col">
            <div class="flex min-h-0 flex-1 flex-col p-5 lg:p-6">
                @php
                    $formId = 'form-cr-eksternal';
                @endphp

                <div class="mb-4 shrink-0 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $edit ? 'Ubah CR Eksternal' : 'Form CR Eksternal' }}</h3>
                        @if ($edit)
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Nomor dokumen: <span class="font-mono font-medium text-gray-900 dark:text-white/90">{{ $externCr->nomor }}</span></p>
                        @else
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Nomor akan dibuat otomatis (format tanggal-Ymd + urut per hari).</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                        <a href="{{ route('admin.cr-eksternal.index') }}" data-no-transition
                            class="inline-flex h-10 items-center rounded-lg border-2 border-slate-600 bg-slate-100 px-4 text-sm font-semibold text-slate-900 shadow-sm transition-colors hover:border-slate-700 hover:bg-slate-200 dark:border-slate-500 dark:bg-slate-800 dark:font-semibold dark:text-gray-50 dark:hover:border-slate-400 dark:hover:bg-slate-700">
                            Kembali ke daftar
                        </a>
                        @if ($edit)
                            <x-async-pdf-link
                                href="{{ route('admin.cr-eksternal.print', $externCr) }}"
                                title="Form permintaan perubahan PDF"
                                class="inline-flex h-10 items-center rounded-lg border border-slate-600 bg-white px-4 text-sm font-semibold text-slate-900 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-500 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700"
                            >
                                Cetak PDF
                            </x-async-pdf-link>
                            <button type="button" title="Riwayat perubahan"
                                class="inline-flex h-10 items-center rounded-lg border border-slate-600 bg-white px-4 text-sm font-semibold text-slate-900 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-500 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700"
                                @click.prevent="$dispatch('open-extern-cr-history', @js([
                                    'fragmentUrl' => route('admin.cr-eksternal.history-modal', $externCr),
                                    'subtitle' => $externCr->nomor,
                                    'namaLabel' => $externCr->nama,
                                ]))">
                                Riwayat
                            </button>
                        @endif
                        <button type="submit" form="{{ $formId }}"
                            class="inline-flex h-10 items-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-md shadow-sky-900/25 ring-2 ring-sky-500/80 transition-colors hover:bg-sky-700 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800 dark:bg-sky-500 dark:text-white dark:ring-sky-400/70 dark:hover:bg-sky-400">
                            {{ $edit ? 'Simpan perubahan' : 'Simpan CR' }}
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden pr-1 [scrollbar-gutter:stable]">
                    <form id="{{ $formId }}" method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-3 pb-2 lg:grid-cols-2 lg:gap-4">
                        @csrf
                        @if ($edit)
                            @method('PUT')
                        @endif

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Tanggal dokumen</label>
                            <input type="date" name="tanggal" required value="{{ old('tanggal', $edit ? $externCr->tanggal->format('Y-m-d') : now()->format('Y-m-d')) }}"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                        </div>

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Divisi pemohon</label>
                            <select name="division_id" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                                <option value="">— pilih divisi —</option>
                                @foreach ($divisionsPemohon as $div)
                                    <option value="{{ $div->id }}" @selected(old('division_id', $edit ? $externCr->division_id : null) == $div->id)>{{ $div->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Nama CR</label>
                            <input type="text" name="nama" required maxlength="255"
                                value="{{ old('nama', $edit ? ($externCr->nama ?? '') : '') }}"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                                placeholder="Judul atau ringkasan permintaan perubahan (wajib)" />
                        </div>

                        <div class="relative z-50 lg:col-span-1"
                            x-data="divisionTerlibatSuggestions({ divisions: {{ \Illuminate\Support\Js::from($terlibatDivisionNames) }} })">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Divisi yang terlibat</label>
                            <div class="relative">
                                <textarea name="divisions_terlibat_text"
                                    x-ref="terlibatTextarea"
                                    rows="1"
                                    placeholder="Ketik nama divisi (saran dari master)."
                                    autocomplete="off"
                                    @focus="refresh()"
                                    @input="refresh()"
                                    @click="refresh()"
                                    @keydown="onKeydown($event)"
                                    @blur="textareaBlurSoon()"
                                    class="h-10 w-full resize-none overflow-x-auto overflow-y-hidden rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">{{ old('divisions_terlibat_text', $involvedTerlibatDefault) }}</textarea>

                                <ul x-cloak
                                    x-show="open"
                                    x-ref="suggestListRoot"
                                    @mousedown.prevent
                                    class="absolute left-0 right-0 top-full z-[10050] mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 text-sm shadow-xl ring-2 ring-slate-900/[0.06] dark:border-slate-600 dark:bg-slate-900 dark:ring-white/10">
                                    <template x-for="(name, idx) in filtered" :key="idx+'-'+name">
                                        <li class="cursor-pointer px-3 py-2 hover:bg-sky-50 dark:hover:bg-white/10"
                                            :class="highlight === idx ? 'bg-sky-100 text-sky-900 dark:bg-sky-800/70 dark:text-sky-100' : 'text-gray-900 dark:text-white/85'"
                                            @mouseenter="highlight = idx"
                                            @click="pick(name)">
                                            <span x-text="name"></span>
                                        </li>
                                    </template>
                                    <li x-show="filtered.length === 0" class="cursor-default px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                                        Tidak ada divisi yang cocok dengan awalan ini. Periksa penulisan nama (sesuai master) atau gunakan koma untuk pisah beberapa divisi.
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Bidang</label>
                            <input type="text" name="bidang" value="{{ old('bidang', $edit ? $externCr->bidang : '') }}"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"
                                placeholder="Bebas teks" />
                        </div>

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">System / aplikasi</label>
                            <select name="extern_cr_application_id" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                                <option value="">— pilih —</option>
                                @foreach ($applications as $app)
                                    <option value="{{ $app->id }}" @selected(old('extern_cr_application_id', $edit ? $externCr->extern_cr_application_id : null) == $app->id)>{{ $app->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Jenis perubahan</label>
                            <select name="jenis_perubahan" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                                <option value="temporary" @selected(old('jenis_perubahan', $edit ? $externCr->jenis_perubahan : 'temporary') === 'temporary')>Sementara</option>
                                <option value="permanent" @selected(old('jenis_perubahan', $edit ? $externCr->jenis_perubahan : '') === 'permanent')>Permanen</option>
                            </select>
                        </div>

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Alasan perubahan</label>
                            <select name="extern_cr_change_reason_id" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                                <option value="">— pilih —</option>
                                @foreach ($changeReasons as $reason)
                                    <option value="{{ $reason->id }}" @selected(old('extern_cr_change_reason_id', $edit ? $externCr->extern_cr_change_reason_id : null) == $reason->id)>{{ $reason->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Prioritas perubahan</label>
                            <select name="prioritas" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                                @foreach (['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi'] as $val => $label)
                                    <option value="{{ $val }}" @selected(old('prioritas', $edit ? $externCr->prioritas : 'sedang') === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">PIC vendor</label>
                            <select name="vendor_pic_user_id" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                                <option value="">— belum ditetapkan —</option>
                                @foreach ($vendorPicUsers ?? [] as $vendorUser)
                                    <option value="{{ $vendorUser->id }}" @selected(old('vendor_pic_user_id', $edit ? $externCr->vendor_pic_user_id : null) == $vendorUser->id)>
                                        {{ $vendorUser->name }}@if ($vendorUser->email) ({{ $vendorUser->email }})@endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pengguna vendor yang ditugaskan akan melihat CR ini di menu CR Eksternal.</p>
                        </div>

                        <div class="lg:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Status</label>
                            <select name="status" required class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                                @foreach (\App\Enums\ExternCrStatus::cases() as $case)
                                    <option value="{{ $case->value }}" @selected(old('status', $edit ? $externCr->status->value : \App\Enums\ExternCrStatus::Open->value) === $case->value)>{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Kondisi saat ini</label>
                            <textarea name="kondisi_saat_ini" rows="2"
                                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">{{ old('kondisi_saat_ini', $edit ? $externCr->kondisi_saat_ini : '') }}</textarea>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Perubahan yang diharapkan</label>
                            <textarea name="perubahan_diharapkan" rows="2"
                                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">{{ old('perubahan_diharapkan', $edit ? $externCr->perubahan_diharapkan : '') }}</textarea>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Risiko terkait bila tidak dilakukan perubahan</label>
                            <textarea name="risiko_bila_tidak" rows="2"
                                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">{{ old('risiko_bila_tidak', $edit ? $externCr->risiko_bila_tidak : '') }}</textarea>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Deskripsi permintaan</label>
                            <textarea name="deskripsi_permintaan" rows="2"
                                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">{{ old('deskripsi_permintaan', $edit ? $externCr->deskripsi_permintaan : '') }}</textarea>
                        </div>

                        @if ($edit && $externCr->attachments->isNotEmpty())
                            <div class="lg:col-span-2 rounded-lg border border-gray-200 bg-slate-50/80 p-3 dark:border-gray-700 dark:bg-white/[0.02]">
                                <div class="mb-2 text-sm font-medium text-gray-800 dark:text-white/90">Lampiran tersimpan ({{ $externCr->attachments->count() }}/5)</div>
                                <ul class="space-y-2">
                                    @foreach ($externCr->attachments as $att)
                                        <li class="flex flex-wrap items-center justify-between gap-2 rounded border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900/50">
                                            <div class="min-w-0">
                                                <span class="break-all font-medium text-gray-800 dark:text-gray-100">{{ $att->original_name ?? basename($att->path) }}</span>
                                                <span class="ml-2 text-xs text-gray-500">{{ $att->size_bytes ? number_format($att->size_bytes / 1024, 1).' KB' : '' }}</span>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('admin.cr-eksternal.attachments.download', [$externCr, $att]) }}" data-no-transition
                                                    class="inline-flex h-8 items-center rounded-lg border border-blue-400 px-2 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:border-blue-500 dark:text-blue-300 dark:hover:bg-blue-900/20">
                                                    Unduh
                                                </a>
                                                <button type="submit" form="extern-cr-del-att-{{ $att->id }}"
                                                    onclick="return confirm('Hapus lampiran ini?')"
                                                    class="inline-flex h-8 items-center rounded-lg border border-red-400 px-2 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20">
                                                    Hapus
                                                </button>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">
                                Unggah lampiran baru (simultan maks {{ $slotLampiran }} file, hingga {{ $slotLampiran }} lembar tersisa)
                            </label>
                            <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.rar,.zip,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp"
                                class="text-sm dark:text-gray-300" @disabled($slotLampiran === 0 && $edit) />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Diperbolehkan: PDF, Word, RAR/ZIP, gambar (jpg/jpeg/png/gif/webp), Excel — maks. 10 MB per file — total tidak lebih dari 5 lampiran per CR.
                            </p>
                            @if ($edit && $slotLampiran === 0)
                                <p class="mt-1 text-xs text-amber-600 dark:text-amber-300">Kuota lampiran penuh. Hapus salah satu file untuk menambahkan yang baru.</p>
                            @endif
                        </div>
                    </form>
                    {{-- DELETE lampiran tidak boleh bersarang dalam form utama—browser akan menutup form CR dan submit bisa salah rute/metode --}}
                    @if ($edit && $externCr->attachments->isNotEmpty())
                        @foreach ($externCr->attachments as $delAtt)
                            <form id="extern-cr-del-att-{{ $delAtt->id }}" method="POST"
                                action="{{ route('admin.cr-eksternal.attachments.delete', [$externCr, $delAtt]) }}" hidden>
                                @csrf
                                @method('DELETE')
                            </form>
                        @endforeach
                    @endif
                </div>
            </div>
        </x-dashboard.accent-card>
    </div>
@endsection
