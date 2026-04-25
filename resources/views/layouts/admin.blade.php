@extends('layouts.fullscreen-layout')

@section('content')
    <div class="h-screen overflow-hidden xl:flex">
        @include('layouts.sidebar')
        @include('layouts.backdrop')

        <div
            class="flex min-h-0 h-full flex-1 flex-col overflow-hidden transition-all duration-300 ease-in-out"
            :class="{
                'xl:ml-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen,
                'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered
            }"
        >
            @include('layouts.app-header')

            <main
                @class([
                    'flex min-h-0 flex-1 flex-col overflow-hidden p-4 text-slate-800 md:p-6 dark:text-gray-100',
                    'w-full max-w-none' => request()->routeIs('admin.dashboard') || request()->routeIs('admin.insert-project.*') || request()->routeIs('admin.daftar-project.*'),
                    'mx-auto max-w-[--breakpoint-2xl]' => !request()->routeIs('admin.dashboard') && !request()->routeIs('admin.insert-project.*') && !request()->routeIs('admin.daftar-project.*'),
                ])
            >
                @yield('admin-content')
            </main>
        </div>
    </div>
@endsection
