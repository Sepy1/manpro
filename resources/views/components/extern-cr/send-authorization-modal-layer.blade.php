{{-- Pilih satu otorisator WA lewat Mahadata --}}
@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('externCrSendAuthModal', () => ({
                    open: false,
                    loading: false,
                    error: '',
                    authorizersJsonUrl: '',
                    sendUrl: '',
                    crNomor: '',
                    reauthorize: false,
                    authorizers: [],
                    selectedId: null,
                    submitting: false,
                    csrfMeta () {
                        const m = document.querySelector('meta[name="csrf-token"]');

                        return m ? (m.getAttribute('content') || '') : '';
                    },
                    resetState () {
                        this.authorizersJsonUrl = '';
                        this.sendUrl = '';
                        this.crNomor = '';
                        this.reauthorize = false;
                        this.authorizers = [];
                        this.selectedId = null;
                        this.error = '';
                        this.loading = false;
                        this.submitting = false;
                    },
                    close () {
                        if (this.open) {
                            document.documentElement.style.overflow = '';
                        }
                        this.open = false;
                        this.resetState();
                    },
                    async openFrom (detail) {
                        this.authorizersJsonUrl = detail.authorizersJsonUrl || '';
                        this.sendUrl = detail.sendUrl || '';
                        this.crNomor = detail.crNomor || '';
                        this.reauthorize = detail.reauthorize === true;
                        if (! this.authorizersJsonUrl || ! this.sendUrl) {
                            return;
                        }

                        this.open = true;
                        this.loading = true;
                        this.error = '';
                        this.authorizers = [];
                        this.selectedId = null;

                        document.documentElement.style.overflow = 'hidden';

                        try {
                            const res = await fetch(this.authorizersJsonUrl, {
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                credentials: 'same-origin',
                            });
                            const body = await res.json().catch(() => ({}));
                            if (! res.ok) {
                                const msg = body.message || body.error || ('Gagal memuat daftar otorisator (' + res.status + ').');
                                throw new Error(msg);
                            }
                            if (body.wa_decision_locked) {
                                throw new Error(body.message || 'CR ini sudah diotorisasi lewat WhatsApp.');
                            }

                            const list = Array.isArray(body.authorizers) ? body.authorizers : [];

                            let firstId = null;
                            list.some((a) => {
                                if (a.checked_default === true || a.checked_default === 1 || a.checked_default === '1') {
                                    firstId = a.id;

                                    return true;
                                }
                                return false;
                            });
                            if (firstId === null) {
                                const found = list.find((a) => a.selectable === true);
                                firstId = found ? found.id : null;
                            }

                            this.authorizers = list;
                            this.selectedId = firstId;
                        } catch (e) {
                            this.error = e.message || 'Gagal memuat daftar.';
                        } finally {
                            this.loading = false;
                        }
                    },
                    selectableCount () {
                        return this.authorizers.filter(function (u) {
                            return u.selectable === true;
                        }).length;
                    },
                    postSelected () {
                        if (! this.sendUrl || this.submitting) {
                            return;
                        }

                        var token = this.csrfMeta();
                        if (! token) {
                            this.error = 'Token keamanan tidak ditemukan. Muat ulang halaman.';
                            return;
                        }

                        const sid = parseInt(this.selectedId, 10);

                        if (! Number.isFinite(sid)) {
                            this.error = 'Pilih satu otorisator.';
                            return;
                        }

                        let okSelectable = false;
                        this.authorizers.forEach(function (a) {
                            if (parseInt(String(a.id), 10) === sid && a.selectable === true) {
                                okSelectable = true;
                            }
                        });
                        if (! okSelectable) {
                            this.error = 'Pilihan tidak valid atau pengguna tidak dapat menerima undangan.';
                            return;
                        }

                        this.error = '';
                        this.submitting = true;

                        const f = document.createElement('form');
                        f.method = 'POST';
                        f.action = this.sendUrl;
                        const csrf = document.createElement('input');
                        csrf.type = 'hidden';
                        csrf.name = '_token';
                        csrf.value = token;
                        f.appendChild(csrf);

                        const idInp = document.createElement('input');
                        idInp.type = 'hidden';
                        idInp.name = 'authorizer_id';
                        idInp.value = String(sid);
                        f.appendChild(idInp);

                        if (this.reauthorize) {
                            const reauth = document.createElement('input');
                            reauth.type = 'hidden';
                            reauth.name = 'reauthorize';
                            reauth.value = '1';
                            f.appendChild(reauth);
                        }

                        document.body.appendChild(f);
                        f.submit();
                    },
                }));
            });
        </script>
    @endpush
@endonce

<div
    x-data="externCrSendAuthModal()"
    @open-extern-cr-send-auth.window="openFrom($event.detail)"
    @keyup.escape.window="if (open) close()"
>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[100060] flex items-center justify-center bg-black/50 p-4 md:p-6"
        @click.self="close()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="extern-cr-send-auth-title"
    >
        <div
            class="relative flex max-h-[min(88vh,860px)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
            @click.stop
        >
            <div class="flex shrink-0 flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                <div class="min-w-0 flex-1">
                    <h2 id="extern-cr-send-auth-title" class="text-base font-semibold text-slate-900 dark:text-white/95" x-text="reauthorize ? 'Otorisasi ulang WhatsApp' : 'Kirim otorisasi WhatsApp'">
                    </h2>
                    <p x-show="crNomor" class="mt-1 font-mono text-sm font-medium text-brand-700 dark:text-brand-300" x-text="crNomor"></p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                        <span x-show="reauthorize">Keputusan otorisasi sebelumnya akan direset, lalu undangan baru dikirim ke </span>
                        <span x-show="! reauthorize">Pilih </span>
                        <strong>satu</strong> pengguna otorisator.
                        <span x-show="! reauthorize"> Yang sudah memberi keputusan atau tidak punya WA valid tidak bisa dipilih.</span>
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="close()"
                >
                    Tutup
                </button>
            </div>

            <div class="relative min-h-0 flex-1 overflow-y-auto p-5 pt-3 [scrollbar-gutter:stable]">
                <div x-show="loading" x-cloak class="flex flex-col items-center justify-center gap-3 py-10 text-sm text-slate-600 dark:text-slate-300">
                    <svg class="h-8 w-8 animate-spin text-teal-600 dark:text-teal-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memuat daftar…</span>
                </div>

                <template x-if="! loading && error">
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/35 dark:bg-rose-950/30 dark:text-rose-200" x-text="error"></div>
                </template>

                <ul x-show="! loading && ! error" class="m-0 list-none space-y-2 p-0" role="list">
                    <template x-for="a in authorizers" :key="a.id">
                        <li class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-600">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input
                                    type="radio"
                                    name="cr_wa_pick_single"
                                    class="mt-1 h-4 w-4 border-slate-400 text-teal-600 focus:ring-teal-600 disabled:opacity-40"
                                    :value="a.id"
                                    x-model.number="selectedId"
                                    :disabled="! a.selectable"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-slate-900 dark:text-white/95" x-text="a.name"></span>
                                    <span class="mt-0.5 block font-mono text-xs text-slate-600 dark:text-slate-400" x-text="a.phone_hint"></span>
                                    <span class="mt-0.5 block text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-500" x-text="a.role"></span>
                                    <template x-if="! a.selectable && a.disabled_reason">
                                        <span class="mt-1 inline-block rounded bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-400" x-text="a.disabled_reason"></span>
                                    </template>
                                </span>
                            </label>
                        </li>
                    </template>
                </ul>

                <template x-if="! loading && ! error && selectableCount() === 0">
                    <p class="py-6 text-center text-sm text-slate-600 dark:text-slate-400">
                        Tidak ada otorisator yang dapat menerima undangan baru (semua tidak valid atau sudah memberi keputusan).
                    </p>
                </template>
            </div>

            <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                    @click="close()"
                    :disabled="submitting"
                >
                    Batal
                </button>
                <button
                    type="button"
                    class="inline-flex items-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-teal-500 dark:hover:bg-teal-400"
                    @click="postSelected()"
                    :disabled="loading || submitting || error || selectableCount() === 0 || selectedId === null"
                >
                    <span x-show="! submitting && ! reauthorize">Kirim WhatsApp</span>
                    <span x-show="! submitting && reauthorize" x-cloak>Kirim otorisasi ulang</span>
                    <span x-show="submitting" x-cloak>Mengirim…</span>
                </button>
            </div>
        </div>
    </div>
</div>
