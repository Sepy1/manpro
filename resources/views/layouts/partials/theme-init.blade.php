@php($applicationTheme = \App\Models\ApplicationThemeSetting::activeTheme())
@php($applicationThemeMode = \App\Models\ApplicationThemeSetting::activeMode())
@php($applicationFontFamily = \App\Models\ApplicationThemeSetting::activeFontFamily())
@php($applicationFontSize = \App\Models\ApplicationThemeSetting::activeFontSize())
@php($applicationFontWeight = \App\Models\ApplicationThemeSetting::activeFontWeight())
@php($applicationFontStack = \App\Models\ApplicationThemeSetting::FONT_FAMILIES[$applicationFontFamily]['stack'])
@php($applicationFontSizeValue = \App\Models\ApplicationThemeSetting::FONT_SIZES[$applicationFontSize]['value'])
@php($applicationFontWeightValue = \App\Models\ApplicationThemeSetting::FONT_WEIGHTS[$applicationFontWeight]['value'])
{{-- Terapkan tema sebelum paint agar tidak terjadi kedipan warna. --}}
<script>
    (function () {
        var root = document.documentElement;
        root.classList.toggle('dark', @json($applicationThemeMode === 'dark'));
        root.dataset.appTheme = @json($applicationTheme);
        root.dataset.appThemeMode = @json($applicationThemeMode);
        root.dataset.appFont = @json($applicationFontFamily);
        root.dataset.appFontSize = @json($applicationFontSize);
        root.dataset.appFontWeight = @json($applicationFontWeight);
        root.style.setProperty('--app-font-family', @json($applicationFontStack));
        root.style.setProperty('--app-font-size', @json($applicationFontSizeValue));
        root.style.setProperty('--app-font-weight', @json($applicationFontWeightValue));
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
