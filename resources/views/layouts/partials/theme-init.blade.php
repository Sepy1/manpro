{{-- Terapkan tema dari localStorage sebelum paint agar tidak kedip (Tailwind darkMode: class) --}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('theme');
            var root = document.documentElement;
            if (stored === 'light') {
                root.classList.remove('dark');
            } else {
                root.classList.add('dark');
            }
        } catch (e) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
