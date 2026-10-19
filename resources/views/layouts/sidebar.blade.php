
@php
    use App\Helpers\MenuHelper;
    $menuGroups = MenuHelper::getMenuGroups();
    $networkSummary = auth()->user()?->role === 'vendor_network'
        ? \App\Support\NetworkStatusSummary::get()
        : null;

    // Get current path
    $currentPath = request()->path();
@endphp

<aside id="sidebar"
    class="fixed left-0 top-0 z-[99999] flex h-screen h-[100dvh] w-[min(88vw,320px)] max-w-[320px] flex-col overflow-hidden rounded-r-2xl border-r border-white/[0.12] bg-gradient-to-br from-[#101b2e] via-[#1a3d62] to-[#132a44] text-blue-50 shadow-[0_28px_64px_-16px_rgba(8,47,73,0.55)] shadow-slate-950/40 ring-1 ring-white/[0.1] backdrop-blur-md transition-[width,transform,box-shadow] duration-300 ease-[cubic-bezier(0.33,1,0.68,1)] dark:border-gray-800/80 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 dark:text-gray-200 dark:shadow-black/45 dark:ring-0 sm:px-0 xl:max-w-none"
    x-data="{
        openSubmenus: {},
        init() {
            // Auto-open Dashboard menu on page load
            this.initializeActiveMenus();
        },
        initializeActiveMenus() {
            const currentPath = '{{ $currentPath }}';

            @foreach ($menuGroups as $groupIndex => $menuGroup)
                @foreach ($menuGroup['items'] as $itemIndex => $item)
                    @if (isset($item['subItems']))
                        // Check if any submenu item matches current path
                        @foreach ($item['subItems'] as $subItem)
                            @isset($subItem['path'])
                                @php
                                    $__menuPath = parse_url($subItem['path'], PHP_URL_PATH) ?? $subItem['path'];
                                @endphp
                                if (currentPath === '{{ ltrim($__menuPath, '/') }}' ||
                                    window.location.pathname === '{{ $__menuPath }}') {
                                    this.openSubmenus['{{ $groupIndex }}-{{ $itemIndex }}'] = true;
                                }
                            @endisset
                        @endforeach
            @endif
            @endforeach
            @endforeach
        },
        toggleSubmenu(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            const newState = !this.openSubmenus[key];

            // Close all other submenus when opening a new one
            if (newState) {
                this.openSubmenus = {};
            }

            this.openSubmenus[key] = newState;
        },
        isSubmenuOpen(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            return this.openSubmenus[key] || false;
        },
        isActive(path) {
            if (!path) return false;
            const [pathnamePart, queryPart] = path.split('?');
            const normalizedPath = pathnamePart.startsWith('/') ? pathnamePart : `/${pathnamePart}`;
            const pathMatches = window.location.pathname === normalizedPath
                || '{{ $currentPath }}' === pathnamePart.replace(/^\//, '');
            if (!pathMatches) return false;
            if (!queryPart) {
                return true;
            }
            const wanted = new URLSearchParams(queryPart);
            const actual = new URLSearchParams(window.location.search);
            for (const [key, value] of wanted.entries()) {
                if (actual.get(key) !== value) return false;
            }
            return true;
        }
    }"
    :class="{
        'xl:w-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
        'xl:w-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }"
    @mouseenter="if (!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)"
    @keydown.escape.window="$store.sidebar.closeMobile()">
    @php
        $livestreamEnabled = request()->routeIs('admin.livestream.player');
        $livestreamToggleUrl = route('admin.livestream.player');
    @endphp
    {{-- Efek metalik (hanya tema terang) --}}
    <div
        class="pointer-events-none absolute inset-0 bg-gradient-to-br from-white/[0.12] via-transparent to-sky-950/45 dark:hidden"
        aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-0 overflow-hidden dark:hidden" aria-hidden="true">
        <div
            class="absolute inset-y-0 -left-full w-[70%] -skew-x-12 bg-gradient-to-r from-transparent via-white/[0.14] to-transparent opacity-90 animate-metallic-shimmer mix-blend-overlay">
        </div>
    </div>
    <div
        class="relative z-10 flex min-h-0 flex-1 flex-col px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-5">
    <!-- Brand + sidebar controls -->
    <div class="pb-4 pt-[max(1rem,env(safe-area-inset-top))] sm:pb-5 sm:pt-6">
        {{-- Desktop XL rapat: tombol collapse + badge CC bertumpuk vertikal, terpusat --}}
        <div
            class="hidden w-full flex-col items-center gap-3 xl:flex"
            x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen"
            x-cloak
        >
            <button
                type="button"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/20 text-blue-100 transition-all duration-200 hover:scale-105 hover:border-white/35 hover:bg-white/15 active:scale-95 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-white/[0.04] lg:h-11 lg:w-11"
                :class="{ 'bg-white/10 shadow-inner shadow-sky-950/20 dark:bg-white/[0.04]': !$store.sidebar.isExpanded }"
                @click="$store.sidebar.toggleExpanded()"
                aria-label="Perlebar atau rapatkan sidebar">
                <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                        fill="currentColor"></path>
                </svg>
            </button>
            <span
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-700 text-xs font-bold text-white shadow-lg shadow-blue-950/40 ring-2 ring-white/25 transition-transform duration-200 hover:scale-105"
            >
                CC
            </span>
        </div>

        {{-- Mobile / sidebar terbuka / hover desktop: satu baris; collapse desktop di kanan --}}
        <div
            class="flex w-full items-center gap-2"
            x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
        >
            <button
                type="button"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/20 text-blue-100 transition-all duration-200 hover:scale-105 hover:border-white/35 hover:bg-white/15 active:scale-95 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-white/[0.04] lg:h-11 lg:w-11 xl:hidden"
                :class="{ 'border-sky-300/60 bg-white/15 dark:border-sky-500/30 dark:bg-sky-500/10': $store.sidebar.isMobileOpen }"
                @click="$store.sidebar.toggleMobileOpen()"
                aria-label="Buka atau tutup menu">
                <svg x-show="!$store.sidebar.isMobileOpen" width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                        fill="currentColor"></path>
                </svg>
                <svg x-show="$store.sidebar.isMobileOpen" class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
                        fill="currentColor" />
                </svg>
            </button>
            <div class="min-w-0 flex-1">
                <a href="/" @click="$store.sidebar.closeMobile()">
                    <span class="block truncate text-lg font-bold leading-snug text-white drop-shadow-sm dark:text-white/90">
                        Control Center
                    </span>
                </a>
            </div>
            <button
                type="button"
                class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/20 text-blue-100 transition-all duration-200 hover:scale-105 hover:border-white/35 hover:bg-white/15 active:scale-95 dark:border-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-white/[0.04] lg:h-11 lg:w-11 xl:flex"
                :class="{ 'bg-white/10 shadow-inner shadow-sky-950/20 dark:bg-white/[0.04]': !$store.sidebar.isExpanded }"
                @click="$store.sidebar.toggleExpanded()"
                aria-label="Perlebar atau rapatkan sidebar">
                <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                        fill="currentColor"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Navigation Menu -->
    <div class="no-scrollbar flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-y-contain scroll-smooth">
        <nav class="mb-6">
            <div class="flex flex-col gap-4">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <div>
                        <!-- Menu Group Title -->
                        <h2 class="mb-3 flex text-xs font-semibold uppercase leading-5 tracking-wider text-sky-200/70 transition-colors duration-200 dark:text-gray-500"
                            :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                            'lg:justify-center' : 'justify-start'">
                            <template
                                x-if="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
                                <span>{{ $menuGroup['title'] }}</span>
                            </template>
                            <template x-if="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path fill-rule="evenodd" clip-rule="evenodd" d="M5.99915 10.2451C6.96564 10.2451 7.74915 11.0286 7.74915 11.9951V12.0051C7.74915 12.9716 6.96564 13.7551 5.99915 13.7551C5.03265 13.7551 4.24915 12.9716 4.24915 12.0051V11.9951C4.24915 11.0286 5.03265 10.2451 5.99915 10.2451ZM17.9991 10.2451C18.9656 10.2451 19.7491 11.0286 19.7491 11.9951V12.0051C19.7491 12.9716 18.9656 13.7551 17.9991 13.7551C17.0326 13.7551 16.2491 12.9716 16.2491 12.0051V11.9951C16.2491 11.0286 17.0326 10.2451 17.9991 10.2451ZM13.7491 11.9951C13.7491 11.0286 12.9656 10.2451 11.9991 10.2451C11.0326 10.2451 10.2491 11.0286 10.2491 11.9951V12.0051C10.2491 12.9716 11.0326 13.7551 11.9991 13.7551C12.9656 13.7551 13.7491 12.9716 13.7491 12.0051V11.9951Z" fill="currentColor"/>
                                </svg>
                            </template>
                        </h2>

                        <!-- Menu Items -->
                        <ul class="flex flex-col gap-1.5">
                            @foreach ($menuGroup['items'] as $itemIndex => $item)
                                <li>
                                    @if (isset($item['subItems']))
                                        <!-- Menu Item with Submenu -->
                                        <button @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }})"
                                            class="menu-item group w-full flex-nowrap items-center"
                                            :class="[
                                                isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ?
                                                'menu-item-active' : 'menu-item-inactive',
                                                !$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen ?
                                                'xl:justify-center' : 'justify-start'
                                            ]">

                                            <!-- Icon -->
                                            <span :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }})
                                                    ? 'text-sky-200 dark:text-sky-400'
                                                    : 'text-sky-100/75 dark:text-gray-400'"
                                                class="transition-transform duration-200 group-hover:scale-110">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            <!-- Text -->
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                x-transition:enter="transition ease-out duration-200"
                                                x-transition:enter-start="opacity-0 -translate-x-1"
                                                x-transition:enter-end="opacity-100 translate-x-0"
                                                :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }})
                                                    ? 'text-white dark:text-sky-300'
                                                    : 'text-blue-50/95 dark:text-gray-200'"
                                                class="menu-item-text flex min-w-0 flex-1 items-center gap-2 whitespace-nowrap text-base font-semibold transition-colors duration-150">
                                                {{ $item['name'] }}
                                                @if (!empty($item['new']))
                                                    <span class="absolute right-10"
                                                        :class="isActive('{{ $item['path'] ?? '' }}') ?
                                                            'menu-dropdown-badge menu-dropdown-badge-active' :
                                                            'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                        new
                                                    </span>
                                                @endif
                                            </span>

                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                class="ml-1 shrink-0 text-sky-100/70 transition-transform duration-200 dark:text-gray-500"
                                                :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ? 'rotate-90 text-sky-200 dark:text-sky-400' : ''"
                                                aria-hidden="true"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                                    <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                                </svg>
                                            </span>

                                        </button>

                                        <!-- Submenu -->
                                        <div
                                            x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) && ($store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen)"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition ease-in duration-150"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1"
                                        >
                                            <ul class="relative mt-2 space-y-0.5 border-l-2 border-white/25 pl-3 ml-8 dark:border-gray-700/90">
                                                @foreach ($item['subItems'] as $subItem)
                                                    <li>
                                                        @if (!empty($subItem['logout']))
                                                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                                                @csrf
                                                                <button
                                                                    type="submit"
                                                                    class="menu-dropdown-item menu-dropdown-item-inactive w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-rose-200 hover:bg-rose-500/20 dark:text-red-400 dark:hover:bg-red-900/20"
                                                                >
                                                                    <span>{{ $subItem['name'] }}</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a href="{{ $subItem['path'] }}" @click="$store.sidebar.closeMobile()" class="group/menu-sub menu-dropdown-item"
                                                                :class="isActive('{{ $subItem['path'] }}') ?
                                                                    'menu-dropdown-item-active' :
                                                                    'menu-dropdown-item-inactive'">
                                                                <span class="mr-2 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-60 transition-opacity group-hover/menu-sub:opacity-100"></span>
                                                                <span>{{ $subItem['name'] }}</span>
                                                                <span class="flex items-center gap-1 ml-auto">
                                                                    @if (!empty($subItem['new']))
                                                                        <span
                                                                            :class="isActive('{{ $subItem['path'] }}') ?
                                                                                'menu-dropdown-badge menu-dropdown-badge-active' :
                                                                                'menu-dropdown-badge menu-dropdown-badge-inactive'">
                                                                            new
                                                                        </span>
                                                                    @endif
                                                                    @if (!empty($subItem['pro']))
                                                                        <span
                                                                            :class="isActive('{{ $subItem['path'] }}') ?
                                                                                'menu-dropdown-badge-pro menu-dropdown-badge-pro-active' :
                                                                                'menu-dropdown-badge-pro menu-dropdown-badge-pro-inactive'">
                                                                            pro
                                                                        </span>
                                                                    @endif
                                                                </span>
                                                            </a>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <!-- Simple Menu Item -->
                                        <a href="{{ $item['path'] }}" @click="$store.sidebar.closeMobile()" class="menu-item group flex-nowrap"
                                            :class="[
                                                isActive('{{ $item['path'] }}') ? 'menu-item-active' :
                                                'menu-item-inactive',
                                                (!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                                                'xl:justify-center' :
                                                'justify-start'
                                            ]">

                                            <!-- Icon -->
                                            <span
                                                :class="isActive('{{ $item['path'] }}')
                                                    ? 'text-sky-200 dark:text-sky-400'
                                                    : 'text-sky-100/75 dark:text-gray-400'"
                                                class="transition-transform duration-200 group-hover:scale-110">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            <!-- Text -->
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                x-transition:enter="transition ease-out duration-200"
                                                x-transition:enter-start="opacity-0 -translate-x-1"
                                                x-transition:enter-end="opacity-100 translate-x-0"
                                                :class="isActive('{{ $item['path'] }}')
                                                    ? 'text-white dark:text-sky-300'
                                                    : 'text-blue-50/95 dark:text-gray-200'"
                                                class="menu-item-text flex items-center gap-2 whitespace-nowrap text-base font-semibold transition-colors duration-150">
                                                {{ $item['name'] }}
                                                @if (!empty($item['new']))
                                                    <span
                                                        class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-brand-500 text-white">
                                                        new
                                                    </span>
                                                @endif
                                            </span>
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        @if ($networkSummary)
            <div
                class="mx-3 mb-3 space-y-2 rounded-xl border border-white/10 bg-slate-950/25 p-3"
                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                x-data="{
                    summary: @js($networkSummary),
                    selected: null,
                    titles: { kas_rto: 'Kantor Kas RTO', cabang_rto: 'Kantor Cabang RTO', slow: 'Kantor Lambat' },
                    toggle(key) { this.selected = this.selected === key ? null : key },
                    items() { return this.selected ? (this.summary[this.selected + '_list'] || []) : [] },
                    date(value) { return value ? new Date(value).toLocaleString('id-ID') : '-' }
                }"
                @network-summary-updated.window="summary = $event.detail"
                x-cloak
            >
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-blue-200/70">Summary Status</p>
                <button type="button" @click="toggle('kas_rto')" class="flex w-full items-center justify-between gap-3 rounded-lg px-1 py-1 text-left text-xs transition hover:bg-white/10" :class="selected === 'kas_rto' ? 'bg-white/10' : ''">
                    <span class="text-gray-300">Kantor Kas RTO</span>
                    <strong id="network-summary-kas-rto" class="rounded-md bg-red-500/20 px-2 py-0.5 text-red-300" x-text="summary.kas_rto"></strong>
                </button>
                <button type="button" @click="toggle('cabang_rto')" class="flex w-full items-center justify-between gap-3 rounded-lg px-1 py-1 text-left text-xs transition hover:bg-white/10" :class="selected === 'cabang_rto' ? 'bg-white/10' : ''">
                    <span class="text-gray-300">Kantor Cabang RTO</span>
                    <strong id="network-summary-cabang-rto" class="rounded-md bg-red-500/20 px-2 py-0.5 text-red-300" x-text="summary.cabang_rto"></strong>
                </button>
                <button type="button" @click="toggle('slow')" class="flex w-full items-center justify-between gap-3 rounded-lg px-1 py-1 text-left text-xs transition hover:bg-white/10" :class="selected === 'slow' ? 'bg-white/10' : ''">
                    <span class="text-gray-300">Kantor Lambat</span>
                    <strong id="network-summary-slow" class="rounded-md bg-amber-500/20 px-2 py-0.5 text-amber-300" x-text="summary.slow"></strong>
                </button>
                <p class="pt-1 text-[10px] text-gray-500">High ping &gt; <span id="network-summary-threshold">{{ $networkSummary['high_ping_threshold_ms'] }}</span> ms</p>

                <div x-show="selected" x-transition x-cloak class="border-t border-white/10 pt-2">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold text-white" x-text="titles[selected]"></p>
                        <button type="button" @click="selected = null" class="rounded px-1.5 py-0.5 text-xs text-gray-400 hover:bg-white/10 hover:text-white" aria-label="Tutup detail">×</button>
                    </div>
                    <div class="max-h-52 space-y-1.5 overflow-y-auto pr-1">
                        <template x-if="items().length === 0">
                            <p class="py-3 text-center text-[11px] text-gray-500">Tidak ada kantor pada status ini.</p>
                        </template>
                        <template x-for="(item, index) in items()" :key="`${selected}-${item.type}-${item.code}-${index}`">
                            <div class="rounded-lg border border-white/10 bg-white/[0.05] px-2.5 py-2 text-[10px] leading-relaxed text-gray-300">
                                <p class="font-semibold text-white"><span x-text="item.code"></span> · <span x-text="item.name"></span></p>
                                <p class="text-gray-400" x-text="item.type"></p>
                                <p x-show="item.parent" class="text-gray-400">Cabang: <span x-text="item.parent"></span></p>
                                <p x-show="item.latency !== null" class="font-medium text-amber-300">Latency: <span x-text="item.latency"></span> ms</p>
                                <p class="text-gray-500">Dicek: <span x-text="date(item.checked_at)"></span></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif

    </div>

    <div class="relative z-10 mt-auto shrink-0 border-t border-white/15 pt-3 dark:border-gray-800">
        <div
            class="flex items-center gap-2"
            :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'xl:flex-col' : ''"
        >
            @if (auth()->user()?->role !== 'vendor_network')
            <a
                href="{{ $livestreamToggleUrl }}"
                @click="$store.sidebar.closeMobile()"
                @class([
                    'flex min-w-0 flex-1 items-center gap-2 rounded-xl py-2 text-left transition-all duration-200 hover:bg-white/12 active:scale-[0.98] dark:hover:bg-white/[0.06]',
                    'bg-white/10 dark:bg-white/[0.08]' => $livestreamEnabled,
                ])
                :class="[
                    (!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
                    'xl:w-full xl:justify-center' : 'justify-start xl:px-1'
                ]"
                aria-label="{{ $livestreamEnabled ? 'Nonaktifkan livestream' : 'Aktifkan livestream' }}"
            >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center text-blue-100 dark:text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6">
                        <path d="M4.5 7.5a3 3 0 0 1 3-3h9a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3v-9Zm3 0a1.5 1.5 0 0 0-1.5 1.5v6a1.5 1.5 0 0 0 1.5 1.5h9A1.5 1.5 0 0 0 18 15v-6A1.5 1.5 0 0 0 16.5 7.5h-9Zm2.25 2.25a.75.75 0 0 1 1.06 0L12 11.94l1.19-1.19a.75.75 0 0 1 1.06 1.06L13.06 13l1.19 1.19a.75.75 0 1 1-1.06 1.06L12 14.06l-1.19 1.19a.75.75 0 1 1-1.06-1.06L10.94 13l-1.19-1.19a.75.75 0 0 1 0-1.06Z" />
                    </svg>
                </span>
                <span
                    x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                    class="truncate text-sm font-semibold text-white dark:text-gray-200"
                >
                    {{ $livestreamEnabled ? 'Livestream aktif' : 'Livestream' }}
                </span>
            </a>
            @endif
        </div>
    </div>
    </div>
</aside>
