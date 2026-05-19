<div
    x-show="$store.sidebar.isMobileOpen"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="$store.sidebar.setMobileOpen(false)"
    class="fixed inset-0 z-50 bg-gray-900/40 backdrop-blur-sm xl:hidden"
    aria-hidden="true"
></div>
