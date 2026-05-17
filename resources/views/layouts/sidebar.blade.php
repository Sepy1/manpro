
@php
    use App\Helpers\MenuHelper;
    $menuGroups = MenuHelper::getMenuGroups();

    // Get current path
    $currentPath = request()->path();
@endphp

<aside id="sidebar"
    class="fixed flex flex-col mt-0 top-0 px-5 left-0 bg-slate-50 text-slate-800 dark:bg-gray-900 dark:text-gray-200 dark:border-gray-800 h-screen z-99999 border-r border-slate-200"
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
        'w-[290px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered,
        'w-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }"
    @mouseenter="if (!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)">
    <!-- Brand + sidebar controls -->
    <div class="pt-6 pb-5">
        <div class="flex items-center gap-2"
            :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ?
            'xl:justify-center' : 'justify-start'">
            <button
                type="button"
                class="flex shrink-0 items-center justify-center w-10 h-10 text-slate-600 border border-slate-200 rounded-lg dark:border-gray-800 dark:text-gray-400 lg:h-11 lg:w-11 xl:hidden"
                :class="{ 'bg-slate-100 dark:bg-white/[0.03]': $store.sidebar.isMobileOpen }"
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
            <button
                type="button"
                class="hidden xl:flex shrink-0 items-center justify-center w-10 h-10 text-slate-600 border border-slate-200 rounded-lg dark:border-gray-800 dark:text-gray-400 lg:h-11 lg:w-11"
                :class="{ 'bg-slate-100 dark:bg-white/[0.03]': !$store.sidebar.isExpanded }"
                @click="$store.sidebar.toggleExpanded()"
                aria-label="Perlebar atau rapatkan sidebar">
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
            <div class="min-w-0 flex-1 xl:flex-initial" x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
                <a href="/">
                    <span class="block text-lg font-bold leading-snug text-slate-800 dark:text-white/90">
                        Management Projects IT BKK Jateng
                    </span>
                </a>
            </div>
            <span
                x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen"
                class="hidden xl:inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-xs font-bold text-white"
            >
                PM
            </span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <div class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <nav class="mb-6">
            <div class="flex flex-col gap-4">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <div>
                        <!-- Menu Group Title -->
                        <h2 class="mb-4 text-xs uppercase flex leading-[20px] text-gray-400"
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
                                                !$store.sidebar.isExpanded && !$store.sidebar.isHovered ?
                                                'xl:justify-center' : 'xl:justify-start'
                                            ]">

                                            <!-- Icon -->
                                            <span :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }})
                                                    ? 'text-sky-400'
                                                    : 'text-slate-400 dark:text-gray-400'"
                                                class="transition-colors duration-150">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            <!-- Text -->
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }})
                                                    ? 'text-sky-400'
                                                    : 'text-slate-700 dark:text-gray-200'"
                                                class="menu-item-text flex items-center gap-2 whitespace-nowrap text-base font-semibold transition-colors duration-150">
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

                                        </button>

                                        <!-- Submenu -->
                                        <div x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) && ($store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen)">
                                            <ul class="mt-2 space-y-1 ml-9">
                                                @foreach ($item['subItems'] as $subItem)
                                                    <li>
                                                        @if (!empty($subItem['logout']))
                                                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                                                @csrf
                                                                <button
                                                                    type="submit"
                                                                    class="menu-dropdown-item menu-dropdown-item-inactive w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                                                >
                                                                    <span>{{ $subItem['name'] }}</span>
                                                                </button>
                                                            </form>
                                                        @else
                                                            <a href="{{ $subItem['path'] }}" class="menu-dropdown-item"
                                                                :class="isActive('{{ $subItem['path'] }}') ?
                                                                    'menu-dropdown-item-active' :
                                                                    'menu-dropdown-item-inactive'"
                                                                :style="isActive('{{ $subItem['path'] }}') ? 'color: rgb(56 189 248);' : ''">
                                                                <span class="mr-2 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-current"></span>
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
                                        <a href="{{ $item['path'] }}" class="menu-item group flex-nowrap"
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
                                                    ? 'text-sky-400'
                                                    : 'text-slate-400 dark:text-gray-400'"
                                                class="transition-colors duration-150">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>

                                            <!-- Text -->
                                            <span
                                                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"
                                                :class="isActive('{{ $item['path'] }}')
                                                    ? 'text-sky-400'
                                                    : 'text-slate-700 dark:text-gray-200'"
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

    </div>
</aside>

<!-- Mobile Overlay -->
<div x-show="$store.sidebar.isMobileOpen" @click="$store.sidebar.setMobileOpen(false)"
    class="fixed z-50 h-screen w-full bg-gray-900/50"></div>
