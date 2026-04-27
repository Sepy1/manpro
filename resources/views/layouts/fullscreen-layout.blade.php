<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | TailAdmin - Laravel Tailwind CSS Admin Dashboard Template</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('sidebar', {
                // Initialize based on screen size
                isExpanded: window.innerWidth >= 1280, // true for desktop, false for mobile
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    // When toggling desktop sidebar, ensure mobile menu is closed
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                    // Don't modify isExpanded when toggling mobile menu
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    // Only allow hover effects on desktop when sidebar is collapsed
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });
        });
    </script>

    <script>
        (function() {
            localStorage.setItem('theme', 'dark');
            document.documentElement.classList.add('dark');
        })();
    </script>
</head>

<body class="h-screen overflow-hidden dark bg-gray-900 text-gray-100" x-data="{ loaded: true, navigating: false }" x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280;
const checkMobile = () => {
    if (window.innerWidth < 1280) {
        $store.sidebar.setMobileOpen(false);
        $store.sidebar.isExpanded = false;
    } else {
        $store.sidebar.isMobileOpen = false;
        $store.sidebar.isExpanded = true;
    }
};
window.addEventListener('resize', checkMobile);
window.addEventListener('DOMContentLoaded', () => { setTimeout(() => loaded = false, 120); });
const isInternalLink = (link) => {
    if (!link) return false;
    if (link.target && link.target !== '_self') return false;
    if (link.hasAttribute('download') || link.hasAttribute('data-no-transition')) return false;
    const href = link.getAttribute('href') || '';
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return false;
    try {
        const url = new URL(link.href, window.location.origin);
        return url.origin === window.location.origin;
    } catch (_) {
        return false;
    }
};
document.addEventListener('click', (event) => {
    if (event.defaultPrevented) return;
    const link = event.target.closest('a[href]');
    if (!isInternalLink(link)) return;
    event.preventDefault();
    navigating = true;
    const shell = document.getElementById('page-transition-root');
    shell?.classList.add('opacity-0', 'scale-[0.995]');
    setTimeout(() => { window.location.href = link.href; }, 140);
});">

    {{-- preloader --}}
    <x-common.preloader/>
    {{-- preloader end --}}

    <div id="page-transition-root" class="transition-all duration-200 ease-out opacity-100 scale-100">
        @yield('content')
    </div>

</body>

@stack('scripts')

</html>
