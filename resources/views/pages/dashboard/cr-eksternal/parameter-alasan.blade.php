@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Parameter: Alasan perubahan CR" />

    <div class="space-y-4" x-data="{ showAddForm: false }">
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="content-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daftar alasan perubahan</h3>
                <button type="button" @click="showAddForm = !showAddForm"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                    Tambah alasan
                </button>
            </div>

            <form x-show="showAddForm" x-cloak method="POST" action="{{ route('admin.parameter.cr-alasan-perubahan.store') }}"
                class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 md:grid-cols-6 dark:border-gray-700">
                @csrf
                <div class="md:col-span-3">
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Mis. Perbaikan gangguan, penyesuaian regulasi..."
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div class="md:col-span-1">
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="65535"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </div>
                <div class="flex items-center md:col-span-1">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                        Aktif
                    </label>
                </div>
                <div class="flex items-center gap-2 md:col-span-1">
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                        Simpan
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="w-[10%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">ID</th>
                            <th class="w-[42%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                            <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Urut</th>
                            <th class="w-[16%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="w-[20%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr x-data="{ edit: false }" class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $item->id }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span x-show="!edit">{{ $item->name }}</span>
                                    <form x-show="edit" x-cloak method="POST" action="{{ route('admin.parameter.cr-alasan-perubahan.update', $item) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ $item->name }}" required class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90 sm:flex-1" />
                                        <input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" max="65535"
                                            class="h-9 w-24 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                                        <label class="inline-flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                            <input type="checkbox" name="is_active" value="1" @checked($item->is_active) class="rounded border-gray-300 dark:border-gray-600" />
                                            Aktif
                                        </label>
                                        <button type="submit" class="inline-flex h-9 shrink-0 items-center rounded-lg border border-brand-500 px-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">Simpan</button>
                                    </form>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span x-show="!edit">{{ $item->sort_order }}</span>
                                </td>
                                <td class="px-3 py-3 text-sm">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $item->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="edit = !edit" class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.parameter.cr-alasan-perubahan.delete', $item) }}" onsubmit="return confirm('Hapus item ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-red-400 px-3 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->hasPages())
                <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-800">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
@endsection
