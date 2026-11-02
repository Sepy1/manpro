@php($applicationTheme = \App\Models\ApplicationThemeSetting::activeTheme())
@php($applicationThemeMode = \App\Models\ApplicationThemeSetting::activeMode())
{{-- Terapkan tema sebelum paint agar tidak terjadi kedipan warna. --}}
<script>
    (function () {
        var root = document.documentElement;
        root.classList.toggle('dark', @json($applicationThemeMode === 'dark'));
        root.dataset.appTheme = @json($applicationTheme);
        root.dataset.appThemeMode = @json($applicationThemeMode);
        try {
            localStorage.setItem('app-theme', @json($applicationTheme));
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
            meta.setAttribute('content', @json($applicationThemeMode));
        } catch (e) {
            /* ignore */
        }
    })();
</script>
