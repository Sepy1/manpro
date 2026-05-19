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
});

Alpine.start();
