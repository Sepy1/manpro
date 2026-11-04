import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('realtimeFilter', () => ({
        loading: false,
        requestController: null,

        apply() {
            const url = new URL(this.$refs.form.action, window.location.origin);
            url.search = new URLSearchParams(new FormData(this.$refs.form)).toString();
            this.load(url.toString());
        },

        paginate(event) {
            const link = event.target.closest('a[href]');
            if (!link || !this.$refs.results.contains(link)) {
                return;
            }

            event.preventDefault();
            this.load(link.href);
        },

        async load(url) {
            this.requestController?.abort();
            const controller = new AbortController();
            this.requestController = controller;
            this.loading = true;

            try {
                const [response] = await Promise.all([
                    fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: controller.signal,
                    }),
                    new Promise((resolve) => setTimeout(resolve, 180)),
                ]);

                if (!response.ok) {
                    throw new Error('Filter gagal dimuat.');
                }

                const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
                const nextResults = documentResult.querySelector('[data-filter-results]');
                if (!nextResults) {
                    throw new Error('Hasil filter tidak ditemukan.');
                }

                this.$refs.results.innerHTML = nextResults.innerHTML;

                const nextCount = documentResult.querySelector('[data-filter-count]');
                if (nextCount && this.$refs.count) {
                    this.$refs.count.innerHTML = nextCount.innerHTML;
                }

                const nextExport = documentResult.querySelector('[data-filter-export]');
                if (nextExport && this.$refs.exportLink) {
                    this.$refs.exportLink.href = nextExport.href;
                }

                const nextMapLink = documentResult.querySelector('[data-filter-attack-map]');
                if (nextMapLink && this.$refs.mapLink) {
                    this.$refs.mapLink.href = nextMapLink.href;
                }

                window.history.replaceState({}, '', url);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.$refs.form.submit();
                }
            } finally {
                if (this.requestController === controller) {
                    this.loading = false;
                }
            }
        },
    }));

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

let sidebarNavigationController = null;

const installNetworkMapAssets = async (nextDocument) => {
    const stack = nextDocument.getElementById('page-script-stack');
    if (!stack) {
        return;
    }

    for (const asset of stack.children) {
        if (asset.tagName === 'STYLE') {
            const style = document.createElement('style');
            style.dataset.partialPageAsset = 'network-map';
            style.textContent = asset.textContent;
            document.head.appendChild(style);
            continue;
        }

        if (asset.tagName === 'LINK' && asset.rel === 'stylesheet') {
            if (document.querySelector(`link[rel="stylesheet"][href="${CSS.escape(asset.href)}"]`)) {
                continue;
            }

            await new Promise((resolve, reject) => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = asset.href;
                link.crossOrigin = asset.crossOrigin;
                link.integrity = asset.integrity;
                link.dataset.partialPageAsset = 'network-map';
                link.addEventListener('load', resolve, { once: true });
                link.addEventListener('error', reject, { once: true });
                document.head.appendChild(link);
            });
            continue;
        }

        if (asset.tagName !== 'SCRIPT') {
            continue;
        }

        if (asset.src) {
            if (document.querySelector(`script[src="${CSS.escape(asset.src)}"]`)) {
                continue;
            }

            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = asset.src;
                script.crossOrigin = asset.crossOrigin;
                script.integrity = asset.integrity;
                script.dataset.partialPageAsset = 'network-map';
                script.addEventListener('load', resolve, { once: true });
                script.addEventListener('error', reject, { once: true });
                document.head.appendChild(script);
            });
            continue;
        }

        const script = document.createElement('script');
        script.dataset.partialPageAsset = 'network-map';
        script.textContent = asset.textContent;
        document.body.appendChild(script);
    }
};

const navigateSidebarContent = async (href, pushState = true) => {
    const currentRoot = document.getElementById('page-transition-root');
    if (!currentRoot) {
        window.location.href = href;
        return;
    }

    sidebarNavigationController?.abort();
    const controller = new AbortController();
    sidebarNavigationController = controller;
    const navigationSpinner = document.getElementById('partial-navigation-spinner');
    navigationSpinner?.classList.remove('hidden');
    navigationSpinner?.classList.add('flex');
    currentRoot.classList.add('blur-[2px]', 'opacity-0', 'pointer-events-none');

    try {
        const [response] = await Promise.all([
            fetch(href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            }),
            new Promise((resolve) => setTimeout(resolve, 180)),
        ]);

        if (!response.ok) {
            throw new Error('Navigasi gagal dimuat.');
        }

        const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
        const nextRoot = nextDocument.getElementById('page-transition-root');
        const finalUrl = response.url || href;
        const partialAssetPath = new URL(finalUrl, window.location.origin).pathname;
        const supportsPartialAssets = ['/admin/map', '/admin/attack-map'].includes(partialAssetPath);
        const nextStackScripts = nextDocument.querySelectorAll('#page-script-stack script');
        const nextRootScripts = nextDocument.querySelectorAll('#page-transition-root script');

        if (!nextRoot || nextRootScripts.length > 0 || (nextStackScripts.length > 0 && !supportsPartialAssets)) {
            window.location.href = finalUrl;
            return;
        }

        document.title = nextDocument.title || document.title;
        window.Alpine.destroyTree(currentRoot);
        currentRoot.replaceWith(nextRoot);
        document.querySelectorAll('[data-partial-page-asset]').forEach((asset) => asset.remove());

        if (supportsPartialAssets) {
            await installNetworkMapAssets(nextDocument);
        }

        if (pushState) {
            window.history.pushState({}, '', finalUrl);
        }

        window.Alpine.initTree(nextRoot);
        window.Alpine.store('sidebar')?.closeMobile();
        window.dispatchEvent(new CustomEvent('app:navigated', { detail: { url: finalUrl } }));
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        window.location.href = href;
    } finally {
        if (sidebarNavigationController === controller) {
            navigationSpinner?.classList.add('hidden');
            navigationSpinner?.classList.remove('flex');
        }
    }
};

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const link = event.target.closest('#sidebar a[href]');
    if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download') || link.hasAttribute('data-no-transition')) {
        return;
    }

    const rawHref = link.getAttribute('href') || '';
    if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:')) {
        return;
    }

    const url = new URL(link.href, window.location.origin);
    if (url.origin !== window.location.origin) {
        return;
    }

    event.preventDefault();
    navigateSidebarContent(url.toString());
});

window.addEventListener('popstate', () => {
    navigateSidebarContent(window.location.href, false);
});
