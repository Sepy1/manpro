{{-- Default tema gelap sebelum paint; hanya terang jika localStorage theme === 'light' (Tailwind darkMode: class) --}}
<script>
    (function () {
        var root = document.documentElement;
        try {
            var stored = localStorage.getItem('theme');
            if (stored === 'light') {
                root.classList.remove('dark');
            } else {
                root.classList.add('dark');
                if (stored !== 'dark') {
                    try {
                        localStorage.setItem('theme', 'dark');
                    } catch (_) {
                        /* private mode / disabled storage */
                    }
                }
            }
        } catch (e) {
            root.classList.add('dark');
        }

        try {
            var scheme = root.classList.contains('dark') ? 'dark' : 'light';
            var meta = document.querySelector('meta[name="color-scheme"]');
            if (!meta) {
                meta = document.createElement('meta');
                meta.setAttribute('name', 'color-scheme');
                document.head.appendChild(meta);
            }
            meta.setAttribute('content', scheme);
        } catch (e) {
            /* ignore */
        }
    })();
</script>
