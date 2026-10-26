@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Parameter: Tipe Perangkat DC/DRC" />

    <div class="space-y-4" x-data="{ showAddForm: @js($errors->any() && old('name')) }">
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
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tipe perangkat DC/DRC</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tipe aktif akan tersedia pada form inventaris perangkat DC/DRC.</p>
                </div>
                <button type="button" @click="showAddForm = !showAddForm"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                    Tambah tipe
                </button>
            </div>

            <form x-show="showAddForm" x-cloak method="POST" action="{{ route('admin.parameter.dc-drc-device-types.store') }}"
                class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 md:grid-cols-6 dark:border-gray-700">
                @csrf
                <div class="md:col-span-3">
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Contoh: Load Balancer"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                </div>
                <div>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="65535" placeholder="Urut"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 dark:border-gray-600">
                    Aktif
                </label>
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">Simpan</button>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="w-[8%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">ID</th>
                            <th class="w-[42%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nama tipe</th>
                            <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Urut</th>
                            <th class="w-[16%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="w-[22%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr x-data="{ edit: false }" class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $item->id }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span x-show="!edit">{{ $item->name }}</span>
                                    <form x-show="edit" x-cloak method="POST" action="{{ route('admin.parameter.dc-drc-device-types.update', $item) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ $item->name }}" required class="h-9 min-w-40 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                                        <input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" max="65535" class="h-9 w-24 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
                                        <label class="inline-flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" @checked($item->is_active) class="rounded"> Aktif</label>
                                        <button type="submit" class="h-9 rounded-lg border border-brand-500 px-3 text-xs font-medium text-brand-600 dark:text-white/90">Simpan</button>
                                    </form>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300"><span x-show="!edit">{{ $item->sort_order }}</span></td>
                                <td class="px-3 py-3 text-sm">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $item->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex gap-2">
                                        <button type="button" @click="edit = !edit" class="h-9 rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 dark:text-white/90">Edit</button>
                                        <form method="POST" action="{{ route('admin.parameter.dc-drc-device-types.destroy', $item) }}" onsubmit="return confirm('Hapus tipe perangkat ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="h-9 rounded-lg border border-red-400 px-3 text-sm font-medium text-red-600 dark:text-red-300">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">Belum ada tipe perangkat.</td></tr>
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
