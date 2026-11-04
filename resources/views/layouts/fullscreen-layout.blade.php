<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.partials.theme-init')

    <title>{{ $title ?? 'Dashboard' }} | Manpro</title>

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
                isDesktop: window.innerWidth >= 1280,

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

                closeMobile() {
                    if (window.innerWidth < 1280) {
                        this.isMobileOpen = false;
                    }
                },

                syncViewport() {
                    const desktop = window.innerWidth >= 1280;
                    if (desktop === this.isDesktop) return;

                    this.isDesktop = desktop;
                    this.isMobileOpen = false;
                    this.isHovered = false;
                    this.isExpanded = desktop;
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
</head>

<body class="h-screen h-[100dvh] overflow-hidden bg-slate-100 text-slate-900 dark:bg-gray-900 dark:text-gray-100" x-data="{ navigating: false }" x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280;
$store.sidebar.isDesktop = window.innerWidth >= 1280;
window.addEventListener('resize', () => $store.sidebar.syncViewport(), { passive: true });
">

    {{-- preloader --}}
    <x-common.preloader/>
    {{-- preloader end --}}

    <div id="partial-navigation-spinner"
        class="pointer-events-none fixed inset-y-0 left-0 right-0 z-[99998] hidden items-center justify-center bg-slate-950/15 backdrop-blur-[1px] transition-[left] duration-300"
        :class="{
            'xl:left-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
            'xl:left-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered
        }"
        role="status"
        aria-live="polite"
        aria-label="Memuat halaman">
        <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-slate-900/90 px-5 py-3 text-sm font-medium text-white shadow-2xl shadow-black/30">
            <span class="h-6 w-6 animate-spin rounded-full border-2 border-white/30 border-t-sky-400" aria-hidden="true"></span>
            <span>Memuat halaman...</span>
        </div>
    </div>

    @yield('content')

    <div id="page-script-stack" class="hidden">
        @stack('scripts')
    </div>

</body>

</html>
