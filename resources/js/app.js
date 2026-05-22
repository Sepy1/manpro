import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.store('theme', {
        dark: document.documentElement.classList.contains('dark'),

        toggle() {
            this.dark = !this.dark;
            try {
                localStorage.setItem('theme', this.dark ? 'dark' : 'light');
            } catch (e) {
                /* ignore */
            }
            document.documentElement.classList.toggle('dark', this.dark);
            try {
                let meta = document.querySelector('meta[name="color-scheme"]');
                if (!meta) {
                    meta = document.createElement('meta');
                    meta.setAttribute('name', 'color-scheme');
                    document.head.appendChild(meta);
                }
                meta.setAttribute('content', this.dark ? 'dark' : 'light');
            } catch (e) {
                /* ignore */
            }
        },
    });

    Alpine.data('assistantChat', (cfg) => ({
        open: false,
        input: '',
        loading: false,
        error: '',
        messages: [],
        ...cfg,
        async send() {
            const text = this.input.trim();
            if (!text || this.loading) {
                return;
            }
            this.error = '';
            this.input = '';
            this.messages.push({ role: 'user', content: text });
            this.loading = true;
            this.$nextTick(() => {
                if (this.$refs.scroll) {
                    this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
                }
            });
            try {
                const payload = {
                    messages: this.messages.map((m) => ({
                        role: m.role,
                        content: m.content,
                    })),
                };
                const res = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.error || data.message || 'Permintaan gagal');
                }
                if (data.message) {
                    this.messages.push({ role: 'assistant', content: data.message });
                } else {
                    throw new Error('Respons kosong');
                }
            } catch (e) {
                this.error = e.message || 'Terjadi kesalahan';
                this.messages.pop();
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    if (this.$refs.scroll) {
                        this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
                    }
                });
            }
        },
    }));

    Alpine.data('divisionTerlibatSuggestions', (config) => ({
        divisions: [...(config.divisions || [])].sort((a, b) => String(a).localeCompare(String(b))),
        filtered: [],
        open: false,
        highlight: 0,

        init() {
            this.textareaEl = () =>
                /** @type {HTMLTextAreaElement|null} */
                (this.$refs.terlibatTextarea ?? null);
        },

        refresh() {
            this.$nextTick(() => this.sync());
        },

        tokenAtCaret(el) {
            const v = el.value;
            const caret = el.selectionStart ?? 0;

            const leftSlice = v.slice(0, caret);
            const anchor = Math.max(
                leftSlice.lastIndexOf(','),
                leftSlice.lastIndexOf(';'),
                leftSlice.lastIndexOf('\n')
            );

            const segStart = anchor + 1;
            const segment = v.slice(segStart, caret);

            let bestStart = null;
            let bestLen = -1;

            for (let s = segStart; s < caret && caret - s <= 140; ++s) {
                const chunk = v.slice(s, caret);
                if (/[\r\n;,]/u.test(chunk)) {
                    continue;
                }
                const needle = chunk.replace(/^@+/, '').trim();
                if (needle.length < 1 || !/^[\p{L}\p{N}]/u.test(needle)) {
                    continue;
                }
                const key = needle.toLowerCase();

                /** prefix nama divisi (sama gagasan penyaringan backend) */
                const hitSome = this.divisions.some((n) =>
                    String(n).toLowerCase().startsWith(key));

                if (hitSome) {
                    const len = caret - s;

                    /** pilih rentang tertutup dari kiri dalam segmen untuk jarum terpanjang */
                    if (len > bestLen) {
                        bestLen = len;
                        bestStart = s;
                    }
                }
            }

            if (bestStart === null) {
                return { start: caret, caret, raw: '', needle: '' };
            }

            const raw = v.slice(bestStart, caret);
            const needle = raw.replace(/^@+/, '').trim();

            return { start: bestStart, caret, raw, needle };
        },

        /** consumed (prevent textarea default behaviour) */
        onKeydown(ev) {
            if (!this.open) {
                return false;
            }

            const k = ev.key;
            if (k === 'Escape') {
                ev.preventDefault();
                this.open = false;

                return true;
            }

            if (this.filtered.length === 0) {
                return false;
            }

            if (k === 'ArrowDown') {
                ev.preventDefault();
                this.highlight = (this.highlight + 1) % this.filtered.length;
                this.scrollHighlightIntoView();

                return true;
            }

            if (k === 'ArrowUp') {
                ev.preventDefault();
                this.highlight =
                    this.highlight <= 0 ? this.filtered.length - 1 : this.highlight - 1;
                this.scrollHighlightIntoView();

                return true;
            }

            if (k === 'Enter' || k === 'Tab') {
                ev.preventDefault();
                const name = this.filtered[this.highlight];
                if (name) {
                    this.pick(name);
                }

                return true;
            }

            return false;
        },

        textareaBlurSoon() {
            setTimeout(() => {
                const ta = this.textareaEl();
                if (!ta || document.activeElement !== ta) {
                    this.open = false;
                }
            }, 200);
        },

        scrollHighlightIntoView() {
            /** @type {HTMLElement|null} */
            const wrap = /** @type {HTMLElement|null} */ this.$refs.suggestListRoot;
            if (!wrap?.children[this.highlight]) {
                return;
            }

            wrap.children[this.highlight].scrollIntoView({ block: 'nearest' });
        },

        sync() {
            const el = this.textareaEl();
            if (!el || this.divisions.length === 0) {
                this.open = false;

                return;
            }

            const { needle } = this.tokenAtCaret(el);

            if (needle.length === 0) {
                this.open = false;

                return;
            }

            const exact = this.divisions.some((n) => n.toLowerCase() === needle.toLowerCase());

            if (exact) {
                this.open = false;

                return;
            }

            const key = needle.toLowerCase();
            const list = this.divisions.filter((n) => n.toLowerCase().startsWith(key));

            this.filtered = [...new Set(list)].slice(0, 48);
            this.highlight = 0;

            this.open = this.filtered.length > 0;
        },

        /**
         * Untuk beberapa divisi: setelah salah satu nama divisi lengkap, berikutnya dipisahkan koma.
         */
        beforeEndsWithDivisionName(before) {
            const upto = before.replace(/\s+$/u, '');
            if (upto.length === 0) {
                return false;
            }

            const sorted = [...this.divisions].sort((a, b) => String(b).length - String(a).length);

            for (let i = 0; i < sorted.length; ++i) {
                const n = String(sorted[i]);
                const nl = n.length;

                if (nl === 0 || nl > upto.length) {
                    continue;
                }
                const tailSlice = upto.slice(-nl);

                if (tailSlice.toLowerCase() !== n.toLowerCase()) {
                    continue;
                }

                const prefIx = upto.length - nl;
                const prevChar = prefIx === 0 ? '' : upto.charAt(prefIx - 1);

                /** awal kalimat boleh; else harus pembatas kata (bukan huruf-angka-hyphen) */
                if (prefIx === 0) {
                    return true;
                }
                if (!/^[\p{L}\p{N}_\-]/u.test(prevChar)) {
                    return true;
                }
            }

            return false;
        },

        pick(name) {
            if (!name) {
                this.open = false;

                return;
            }

            const el = this.textareaEl();
            if (!el) {
                return;
            }

            const { start } = this.tokenAtCaret(el);
            const caretNow = el.selectionStart ?? start;

            const before = el.value.slice(0, start);
            const tail = el.value.slice(caretNow);

            const trimBefore = before.replace(/\s+$/u, '');
            const endsListSep = trimBefore.endsWith(',') || trimBefore.endsWith(';');
            let insert =
                trimBefore !== '' &&
                !endsListSep &&
                this.beforeEndsWithDivisionName(before)
                    ? `, ${name}`
                    : name;

            /** beri pemisah bila kata berikutnya menempel */
            let sep = '';
            if (
                tail.length > 0 &&
                !/^[\s,;:]/.test(tail) &&
                /[\p{L}\p{N}_]/u.test(tail.charAt(0))
            ) {
                sep = ' ';
            }

            el.value = `${before}${insert}${sep}${tail}`;

            const pos = `${before}${insert}${sep}`.length;
            el.selectionStart = pos;
            el.selectionEnd = pos;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.focus({ preventScroll: true });

            this.open = false;
        },
    }));
});

Alpine.start();
