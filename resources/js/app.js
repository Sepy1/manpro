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
});

Alpine.start();
