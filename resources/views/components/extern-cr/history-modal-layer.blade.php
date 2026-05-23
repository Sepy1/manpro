{{-- Kartu popup: fetch HTML dari route admin.cr-eksternal.history-modal --}}
@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('externCrHistoryModal', () => ({
                    isOpen: false,
                    loading: false,
                    error: '',
                    html: '',
                    subtitle: '',
                    namaLabel: '',
                    async openFrom (detail) {
                        const fragmentUrl = detail?.fragmentUrl;
                        if (! fragmentUrl) {
                            return;
                        }
                        this.isOpen = true;
                        this.loading = true;
                        this.error = '';
                        this.html = '';
                        this.subtitle = detail.subtitle || '';
                        this.namaLabel = detail.namaLabel || '';
                        document.documentElement.style.overflow = 'hidden';
                        try {
                            const res = await fetch(fragmentUrl, {
                                headers: {
                                    Accept: 'text/html',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                            });
                            if (! res.ok) {
                                throw new Error('bad_status');
                            }
                            this.html = await res.text();
                        } catch (e) {
                            this.error = 'Gagal memuat riwayat.';
                        } finally {
                            this.loading = false;
                        }
                    },
                    close () {
                        if (this.isOpen) {
                            document.documentElement.style.overflow = '';
                        }
                        this.isOpen = false;
                        this.loading = false;
                        this.error = '';
                        this.html = '';
                        this.subtitle = '';
                        this.namaLabel = '';
                    },
                }));
            });
        </script>
    @endpush
@endonce

<div
    x-data="externCrHistoryModal()"
    @open-extern-cr-history.window="openFrom($event.detail)"
    @keyup.escape.window="isOpen && close()"
>
    <div
        x-show="isOpen"
        x-cloak
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[100050] flex items-center justify-center bg-black/50 p-4 md:p-6"
        @click.self="close()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="extern-cr-history-title"
        :aria-busy="loading ? 'true' : 'false'"
    >
        <div
            class="relative flex max-h-[min(85vh,820px)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
            @click.stop
        >
            <div class="flex shrink-0 flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <div class="min-w-0 flex-1">
                    <h2 id="extern-cr-history-title" class="text-base font-semibold text-slate-900 dark:text-white/95">Riwayat CR Eksternal</h2>
                    <p x-show="subtitle" x-cloak x-text="subtitle" class="mt-1 font-mono text-sm font-medium text-brand-700 dark:text-brand-300"></p>
                    <p x-show="namaLabel" x-cloak x-text="namaLabel" class="mt-0.5 line-clamp-2 text-xs text-slate-600 dark:text-slate-400"></p>
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="close()"
                >
                    Tutup
                </button>
            </div>

            <div class="relative min-h-0 flex-1 overflow-y-auto p-5 pt-4 [scrollbar-gutter:stable]">
                <div x-show="loading" x-cloak class="flex flex-col items-center justify-center gap-3 py-10 text-sm text-slate-600 dark:text-slate-300">
                    <svg class="h-8 w-8 animate-spin text-sky-600 dark:text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memuat riwayat…</span>
                </div>

                <p x-show="!loading && error" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200" x-text="error"></p>

                <div x-show="!loading && html" x-cloak class="relative" x-html="html"></div>
            </div>
        </div>
    </div>
</div>
