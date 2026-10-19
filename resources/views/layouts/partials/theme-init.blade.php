{{-- Aplikasi hanya menggunakan tema gelap. Terapkan sebelum paint agar tidak berkedip terang. --}}
<script>
    (function () {
        var root = document.documentElement;
        root.classList.add('dark');
        try {
            localStorage.removeItem('theme');
        } catch (_) {
            /* private mode / disabled storage */
        }

        try {
            var meta = document.querySelector('meta[name="color-scheme"]');
            if (!meta) {
                meta = document.createElement('meta');
                meta.setAttribute('name', 'color-scheme');
                document.head.appendChild(meta);
            }
            meta.setAttribute('content', 'dark');
        } catch (e) {
            /* ignore */
        }
    })();
</script>
