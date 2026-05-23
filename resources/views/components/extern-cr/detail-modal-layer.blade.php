@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('externCrDetailModal', () => ({
                    open: false,
                    loading: false,
                    error: '',
                    detailHtml: '',
                    fragmentUrl: '',
                    updateUrl: '',
                    crId: null,
                    bannerOk: '',
                    bannerErr: '',
                    statusBusy: false,
                    subtitle: '',
                    async openFrom (detail) {
                        this.fragmentUrl = detail?.fragmentUrl || '';
                        this.updateUrl = detail?.updateUrl || '';
                        this.crId = typeof detail?.crId !== 'undefined' ? detail.crId : null;
                        this.subtitle = detail?.subtitle || '';
                        if (! this.fragmentUrl) {
                            return;
                        }
                        this.open = true;
                        this.bannerOk = '';
                        this.bannerErr = '';
                        await this.fetchDetailHtml();
                        document.documentElement.style.overflow = 'hidden';
                    },
                    async fetchDetailHtml () {
                        this.loading = true;
                        this.error = '';
                        this.detailHtml = '';
                        try {
                            const res = await fetch(this.fragmentUrl, {
                                headers: {
                                    Accept: 'text/html',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                            });
                            if (! res.ok) {
                                throw new Error('bad_body');
                            }
                            this.detailHtml = await res.text();
                        } catch (e) {
                            this.error = 'Gagal memuat detail CR.';
                        } finally {
                            this.loading = false;
                        }
                    },
                    csrfToken () {
                        var m = document.querySelector('meta[name="csrf-token"]');

                        return m ? m.getAttribute('content') : '';
                    },
                    async submitStatusUpdate () {
                        if (! this.updateUrl) {
                            return;
                        }

                        var sel = document.getElementById('extern-cr-detail-status-select');
                        var ta = document.getElementById('extern-cr-detail-status-note');
                        if (! sel) {
                            return;
                        }

                        this.statusBusy = true;
                        this.bannerOk = '';
                        this.bannerErr = '';

                        var noteVal = ta ? ta.value : '';

                        try {
                            var res = await fetch(this.updateUrl, {
                                method: 'PATCH',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': this.csrfToken(),
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    status: sel.value,
                                    note: noteVal.trim() !== '' ? noteVal : null,
                                }),
                            });
                            var data = {};
                            try {
                                data = await res.json();
                            } catch (_) {
                                data = {};
                            }

                            if (! res.ok || ! data.ok) {
                                this.bannerErr = data.message || (data.errors?.status?.[0]) || 'Gagal memperbarui status.';

                                return;
                            }

                            this.bannerOk = data.message || 'Status diperbarui.';
                            if (typeof this.crId !== 'undefined' && this.crId !== null && data.status_label) {
                                window.dispatchEvent(new CustomEvent('extern-cr-row-status', {
                                    detail: { id: this.crId, label: data.status_label },
                                }));
                            }
                            await this.fetchDetailHtml();
                        } catch (e) {
                            this.bannerErr = 'Gagal memperbarui status.';
                        } finally {
                            this.statusBusy = false;
                        }
                    },
                    close () {
                        if (this.open) {
                            document.documentElement.style.overflow = '';
                        }
                        this.open = false;
                        this.loading = false;
                        this.error = '';
                        this.detailHtml = '';
                        this.fragmentUrl = '';
                        this.updateUrl = '';
                        this.crId = null;
                        this.bannerOk = '';
                        this.bannerErr = '';
                        this.subtitle = '';
                    },
                }));
            });
        </script>
    @endpush
@endonce

<div
    x-data="externCrDetailModal()"
    @open-extern-cr-detail.window="openFrom($event.detail)"
    @keyup.escape.window="open && close()"
>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[100050] flex items-center justify-center bg-black/50 p-4 md:p-6"
        @click.self="close()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="extern-cr-detail-title"
        :aria-busy="loading ? 'true' : 'false'"
    >
        <div
            class="relative flex max-h-[min(92vh,900px)] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
            @click.stop
        >
            <div class="flex shrink-0 flex-wrap items-start justify-between gap-3 border-b border-slate-200 bg-slate-50/80 px-5 py-4 dark:border-slate-700 dark:bg-slate-900/70">
                <div class="min-w-0 flex-1">
                    <h2 id="extern-cr-detail-title" class="text-base font-semibold text-slate-900 dark:text-white/95">Detail CR Eksternal</h2>
                    <p x-show="subtitle" x-cloak class="mt-1 font-mono text-sm font-medium tabular-nums text-slate-700 dark:text-slate-300" x-text="subtitle"></p>
                </div>
                <button type="button" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800" @click="close()">
                    Tutup
                </button>
            </div>

            <template x-if="bannerOk !== ''">
                <div class="shrink-0 border-b border-emerald-100 bg-emerald-50 px-5 py-2 text-xs font-medium text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/60 dark:text-emerald-100" x-text="bannerOk"></div>
            </template>
            <template x-if="bannerErr !== ''">
                <div class="shrink-0 border-b border-red-100 bg-red-50 px-5 py-2 text-xs font-medium text-red-900 dark:border-red-900/40 dark:bg-red-950/50 dark:text-red-100" x-text="bannerErr"></div>
            </template>

            <div class="relative min-h-0 flex-1 overflow-y-auto p-5 pt-4 [scrollbar-gutter:stable]">
                <div x-show="loading" x-cloak class="flex flex-col items-center justify-center gap-3 py-10 text-sm text-slate-600 dark:text-slate-300">
                    <svg class="h-8 w-8 animate-spin text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memuat…</span>
                </div>

                <p x-show="!loading && error" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200" x-text="error"></p>

                <div x-show="!loading && detailHtml" x-cloak class="relative" x-html="detailHtml"></div>
            </div>

            <div x-show="!loading && detailHtml !== '' && !error" x-cloak class="shrink-0 border-t border-slate-200 bg-slate-50 px-5 py-3.5 dark:border-slate-700 dark:bg-slate-900/90">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a data-no-transition :href="crId !== null ? '{{ url('/admin/cr-eksternal') }}/' + crId + '/edit' : '#'" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-slate-700 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white" x-show="crId !== null">Buka formulir lengkap</a>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 disabled:opacity-50 dark:bg-sky-500 dark:hover:bg-sky-400"
                        :disabled="statusBusy || loading"
                        @click.prevent="submitStatusUpdate()"
                    >
                        <span x-show="statusBusy">Menyimpan…</span><span x-show="!statusBusy">Update status CR</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
