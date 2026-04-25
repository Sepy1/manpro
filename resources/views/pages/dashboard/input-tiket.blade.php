@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Input Tiket" />

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <h3 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90">Form Input Tiket</h3>

        <form class="space-y-5">
            <div>
                <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Judul Tiket</label>
                <input type="text" placeholder="Masukkan judul tiket"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div>
                <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Prioritas</label>
                <select
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
                    <option>Low</option>
                    <option selected>Medium</option>
                    <option>High</option>
                </select>
            </div>

            <div>
                <label class="mb-2.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Deskripsi</label>
                <textarea rows="5" placeholder="Masukkan deskripsi tiket"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"></textarea>
            </div>

            <button type="button"
                class="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">
                Simpan Tiket
            </button>
        </form>
    </div>
@endsection
