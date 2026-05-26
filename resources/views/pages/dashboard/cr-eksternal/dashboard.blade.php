@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="CR Eksternal - Dashboard CR" />
    @php
        $canDragDrop = auth()->user()?->role === 'admin' && !request()->boolean('livestream_embed');
    @endphp

    <div
        class="flex h-full w-full min-h-0 flex-1 flex-col gap-4"
        x-data="{
            badgeBaseClass: @js(\App\Enums\ExternCrStatus::listBadgeShellClasses()),
            badgeClassMap: @js(\App\Enums\ExternCrStatus::listBadgeClassMap()),
            canDragDrop: @js($canDragDrop),
            statusUpdateUrlTemplate: @js(url('/admin/cr-eksternal/__CR_ID__/status')),
            dragCardId: null,
            dragFromStatus: '',
            dropTargetStatus: '',
            dropTargetTitle: '',
            dropOpen: false,
            dropBusy: false,
            dropNote: '',
            dropFiles: null,
            dropError: '',
            openDropModal(cardId, fromStatus, toStatus, toTitle) {
                if (!this.canDragDrop) { return; }
                this.dragCardId = Number(cardId || 0);
                this.dragFromStatus = String(fromStatus || '');
                this.dropTargetStatus = String(toStatus || '');
                this.dropTargetTitle = String(toTitle || '');
                this.dropOpen = true;
                this.dropBusy = false;
                this.dropNote = '';
                this.dropFiles = null;
                this.dropError = '';
                document.documentElement.style.overflow = 'hidden';
            },
            closeDropModal() {
                this.dropOpen = false;
                this.dropBusy = false;
                this.dropError = '';
                this.dropNote = '';
                this.dropFiles = null;
                this.dragCardId = null;
                this.dragFromStatus = '';
                this.dropTargetStatus = '';
                this.dropTargetTitle = '';
                document.documentElement.style.overflow = '';
            },
            dragStart(event, cardId, status) {
                if (!this.canDragDrop) { return; }
                this.dragCardId = Number(cardId || 0);
                this.dragFromStatus = String(status || '');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(cardId || ''));
            },
            dragOver(event) {
                if (!this.canDragDrop) { return; }
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            },
            dragEnter(event) {
                if (!this.canDragDrop) { return; }
                event.currentTarget.classList.add('ring-2', 'ring-brand-400');
            },
            dragLeave(event) {
                event.currentTarget.classList.remove('ring-2', 'ring-brand-400');
            },
            dropOnColumn(event, targetStatus, targetTitle) {
                if (!this.canDragDrop) { return; }
                event.preventDefault();
                event.currentTarget.classList.remove('ring-2', 'ring-brand-400');
                if (!this.dragCardId || !targetStatus || this.dragFromStatus === targetStatus) {
                    return;
                }
                this.openDropModal(this.dragCardId, this.dragFromStatus, targetStatus, targetTitle);
            },
            csrfToken() {
                const meta = document.querySelector('meta[name=csrf-token]');
                return meta ? meta.getAttribute('content') : '';
            },
            refreshColumnCounts() {
                document.querySelectorAll('[data-column-status]').forEach((columnEl) => {
                    const status = columnEl.getAttribute('data-column-status') || '';
                    const cardsWrap = columnEl.querySelector('[data-cards-wrap]');
                    const counterEl = columnEl.querySelector('[data-column-count]');
                    if (!cardsWrap || !counterEl) { return; }
                    const count = cardsWrap.querySelectorAll('[data-cr-id]').length;
                    counterEl.textContent = String(count);
                });
            },
            async submitDropStatus() {
                if (!this.dragCardId || !this.dropTargetStatus) { return; }
                this.dropBusy = true;
                this.dropError = '';

                const url = this.statusUpdateUrlTemplate.replace('__CR_ID__', String(this.dragCardId));
                const formData = new FormData();
                formData.append('_method', 'PATCH');
                formData.append('status', this.dropTargetStatus);
                if (String(this.dropNote || '').trim() !== '') {
                    formData.append('note', String(this.dropNote).trim());
                }
                const files = this.$refs.dropAttachments?.files || [];
                Array.prototype.forEach.call(files, (file, index) => {
                    if (index < 3) {
                        formData.append('status_attachments[]', file);
                    }
                });

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrfToken(),
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        this.dropError = data.message || data.errors?.status_attachments?.[0] || data.errors?.status?.[0] || 'Gagal memperbarui status.';
                        return;
                    }

                    const cardEl = document.querySelector('[data-cr-id=\'' + String(this.dragCardId) + '\']');
                    const targetCardsWrap = document.querySelector('[data-column-status=\'' + this.dropTargetStatus + '\'] [data-cards-wrap]');
                    if (cardEl && targetCardsWrap) {
                        targetCardsWrap.prepend(cardEl);
                        cardEl.setAttribute('data-status', this.dropTargetStatus);
                        const chip = cardEl.querySelector('.cr-status-chip');
                        if (chip) {
                            chip.textContent = data.status_label || this.dropTargetTitle;
                            const mappedClass = this.badgeClassMap[this.dropTargetStatus];
                            if (mappedClass) {
                                chip.className = this.badgeBaseClass + ' ' + mappedClass;
                            }
                        }
                    }
                    this.refreshColumnCounts();
                    window.dispatchEvent(new CustomEvent('extern-cr-row-status', {
                        detail: {
                            id: this.dragCardId,
                            label: data.status_label || this.dropTargetTitle,
                            status: this.dropTargetStatus,
                        },
                    }));
                    this.closeDropModal();
                } catch (_) {
                    this.dropError = 'Gagal memperbarui status.';
                } finally {
                    this.dropBusy = false;
                }
            },
            updateCardStatus (d) {
                if (!d || typeof d.id === 'undefined') { return; }
                const chips = document.querySelectorAll('[data-cr-id=\'' + String(d.id) + '\'] .cr-status-chip');
                chips.forEach((chip) => {
                    if (d.label) { chip.textContent = d.label; }
                    if (d.status && this.badgeClassMap[d.status]) {
                        chip.className = this.badgeBaseClass + ' ' + this.badgeClassMap[d.status];
                    }
                });
            }
        }"
        @extern-cr-row-status.window="updateCardStatus($event.detail)"
    >
        <x-dashboard.accent-card accent-index="2" shell-overflow="hidden" class="flex h-full min-h-0 flex-1 flex-col" padding="p-4 lg:p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Dashboard CR (Board)</h3>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.cr-eksternal.index') }}"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-400 px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-500 dark:text-slate-200 dark:hover:bg-slate-800">
                        Lihat tabel
                    </a>
                    <a href="{{ route('admin.cr-eksternal.create') }}" data-no-transition
                        class="inline-flex h-9 items-center rounded-lg bg-sky-600 px-3 text-xs font-semibold text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-400">
                        Tambah CR
                    </a>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-hidden">
                <div class="flex h-full min-h-0 gap-2">
                    @foreach ($columns as $column)
                        @php
                            $status = $column['status'];
                            $cards = $items->filter(fn ($item) => $item->status === $status)->values();
                        @endphp
                        <section
                            data-column-status="{{ $status->value }}"
                            @dragover="dragOver($event)"
                            @dragenter="dragEnter($event)"
                            @dragleave="dragLeave($event)"
                            @drop="dropOnColumn($event, '{{ $status->value }}', '{{ $status->label() }}')"
                            class="flex h-full min-h-0 min-w-0 flex-1 basis-0 flex-col rounded-xl border border-gray-200 bg-gray-50/70 p-2.5 transition dark:border-gray-700 dark:bg-slate-900/70"
                        >
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <h4 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">
                                    {{ $column['title'] }}
                                </h4>
                                <span data-column-count class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-100">
                                    {{ $cards->count() }}
                                </span>
                            </div>

                            <div class="min-h-0 flex-1 rounded-lg">
                            <div data-cards-wrap class="min-h-0 h-full space-y-2 overflow-y-auto pr-1">
                                @forelse ($cards as $row)
                                    <article
                                        data-cr-id="{{ $row->id }}"
                                        data-status="{{ $row->status->value }}"
                                        draggable="{{ $canDragDrop ? 'true' : 'false' }}"
                                        @dragstart="dragStart($event, {{ $row->id }}, $event.currentTarget.dataset.status || '')"
                                        class="rounded-xl border border-slate-300 bg-white p-3 shadow-sm transition hover:border-brand-400 hover:shadow-md dark:border-slate-600 dark:bg-slate-800"
                                    >
                                        <div class="mb-2 flex items-start justify-between gap-2">
                                            <button
                                                type="button"
                                                draggable="false"
                                                class="line-clamp-2 text-left text-sm font-semibold text-gray-800 underline-offset-2 hover:underline dark:text-white/90"
                                                @click.prevent="$dispatch('open-extern-cr-detail', @js([
                                                    'fragmentUrl' => route('admin.cr-eksternal.detail-modal', $row),
                                                    'updateUrl' => route('admin.cr-eksternal.status', $row),
                                                    'crId' => $row->id,
                                                    'subtitle' => $row->nomor,
                                                ]))"
                                            >
                                                {{ $row->nama ?: $row->nomor }}
                                            </button>
                                            <span class="cr-status-chip {{ \App\Enums\ExternCrStatus::listBadgeShellClasses() }} {{ $row->status->listBadgeClasses() }}">
                                                {{ $row->status->label() }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row->nomor }}</p>
                                        <div class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                                            <p><span class="font-medium">Divisi:</span> {{ $row->division?->name ?? '-' }}</p>
                                            <p><span class="font-medium">Sistem:</span> {{ $row->application?->name ?? '-' }}</p>
                                            <p><span class="font-medium">Tanggal:</span> {{ $row->tanggal?->format('d/m/Y') ?? '-' }}</p>
                                        </div>
                                        <div class="mt-3 flex items-center justify-end gap-2" @click.stop>
                                            <button
                                                type="button"
                                                draggable="false"
                                                title="Riwayat perubahan"
                                                class="inline-flex h-7 items-center rounded-lg border border-slate-400 px-2 text-[11px] font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-500 dark:text-slate-200 dark:hover:bg-slate-700/70"
                                                @click.prevent="$dispatch('open-extern-cr-history', @js([
                                                    'fragmentUrl' => route('admin.cr-eksternal.history-modal', $row),
                                                    'subtitle' => $row->nomor,
                                                    'namaLabel' => $row->nama,
                                                ]))"
                                            >
                                                Riwayat
                                            </button>
                                        </div>
                                    </article>
                                @empty
                                    <div class="rounded-lg border border-dashed border-gray-300 px-3 py-6 text-center text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                        Belum ada CR.
                                    </div>
                                @endforelse
                            </div>
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
        </x-dashboard.accent-card>

        <div
            x-show="dropOpen"
            x-cloak
            class="fixed inset-0 z-[100070] flex items-center justify-center bg-black/50 p-4"
            @click.self="closeDropModal()"
        >
            <div class="flex w-full max-w-lg flex-col rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white/95">Pindahkan Status CR</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        Status tujuan: <span class="font-semibold" x-text="dropTargetTitle"></span>
                    </p>
                </div>
                <div class="space-y-3 p-5">
                    <template x-if="dropError">
                        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-200" x-text="dropError"></div>
                    </template>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Note perubahan status</label>
                        <textarea x-model="dropNote" rows="4" placeholder="Tulis catatan perubahan status..."
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Upload lampiran (maks 3 file)</label>
                        <input x-ref="dropAttachments" type="file" multiple
                            class="block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-200 file:px-3 file:py-2 file:text-xs file:font-semibold dark:text-slate-200 dark:file:bg-slate-700">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-3 dark:border-slate-700">
                    <button type="button" @click="closeDropModal()"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        Batal
                    </button>
                    <button type="button" :disabled="dropBusy" @click="submitDropStatus()"
                        class="inline-flex h-9 items-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50 dark:bg-sky-500 dark:hover:bg-sky-400">
                        <span x-show="!dropBusy">Simpan perubahan</span>
                        <span x-show="dropBusy">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
