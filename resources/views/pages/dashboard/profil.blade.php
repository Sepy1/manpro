@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Profil" />

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <h3 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90">Form Profil</h3>

        <form class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Nama</label>
                <input type="text" value="{{ auth()->user()?->name }}"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
            </div>

            <div>
                <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
                <input type="email" value="{{ auth()->user()?->email }}"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
            </div>

            <div class="md:col-span-2">
                <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Role</label>
                <input type="text" value="{{ strtoupper(auth()->user()?->role ?? '-') }}" readonly
                    class="h-11 w-full cursor-not-allowed rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300" />
            </div>

            <div class="md:col-span-2">
                <button type="button"
                    class="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
@endsection
