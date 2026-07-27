@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Manajemen API Key" />

    <div class="space-y-4" x-data="{ showAddForm: false }">
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        @if (session('new_api_key'))
            @php($newApiKey = session('new_api_key'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">API key baru dibuat untuk {{ $newApiKey['name'] }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-amber-800 dark:text-amber-200">Key plaintext hanya ditampilkan sekali di halaman ini. Simpan sekarang sebelum berpindah halaman.</p>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-gray-900 dark:border-amber-900/40 dark:bg-gray-900 dark:text-white/90">
                        {{ $newApiKey['key'] }}
                    </div>
                </div>
            </div>
        @endif

        <div class="content-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">API Key CR Eksternal</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Kelola key untuk integrasi aplikasi lain dengan board CR eksternal.</p>
                </div>
                <button type="button" @click="showAddForm = !showAddForm"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                    Add API Key
                </button>
            </div>

            <form x-show="showAddForm" x-cloak method="POST" action="{{ route('admin.parameter.api-key.store') }}" class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 md:grid-cols-6 dark:border-gray-700">
                @csrf
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Nama key, mis. sambatan"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90 md:col-span-3" />
                <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90 md:col-span-2" />
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                        Simpan
                    </button>
                    <button type="button" @click="showAddForm = false" class="inline-flex h-9 items-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/10">
                        Batal
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="w-[6%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">ID</th>
                            <th class="w-[20%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nama</th>
                            <th class="w-[14%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Prefix</th>
                            <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="w-[18%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Last Used</th>
                            <th class="w-[18%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Expired At</th>
                            <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($apiKeys as $apiKey)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $apiKey->id }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    <span class="font-medium text-gray-900 dark:text-white/90">{{ $apiKey->name }}</span>
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $apiKey->key_prefix }}</td>
                                <td class="px-3 py-3 text-sm">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $apiKey->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $apiKey->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $apiKey->last_used_at?->format('Y-m-d H:i') ?? '—' }}
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $apiKey->expires_at?->format('Y-m-d H:i') ?? '—' }}
                                </td>
                                <td class="px-3 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('admin.parameter.api-key.update', $apiKey) }}" class="inline-flex items-center gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="name" value="{{ $apiKey->name }}" />
                                            <input type="hidden" name="is_active" value="{{ $apiKey->is_active ? 0 : 1 }}" />
                                            <input type="hidden" name="expires_at" value="{{ optional($apiKey->expires_at)->format('Y-m-d\\TH:i') }}" />
                                            <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                                {{ $apiKey->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.parameter.api-key.delete', $apiKey) }}" onsubmit="return confirm('Hapus API key ini?')">
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
                                <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada API key.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-800">
                {{ $apiKeys->links() }}
            </div>
        </div>
    </div>
@endsection
