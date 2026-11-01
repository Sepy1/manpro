@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Log Security" />

    <x-dashboard.accent-card
        accent-index="0"
        shell-overflow="visible"
        class="flex min-h-0 h-full flex-col"
        padding="p-5 lg:p-6"
    >
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Log Serangan FortiGate</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Menampilkan {{ number_format($filteredCount) }} dari {{ number_format($totalCount) }} log.
            </p>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="GET" action="{{ route('admin.log-security.index') }}" class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
            <input
                type="text"
                name="keyword"
                value="{{ $filters['keyword'] }}"
                placeholder="Cari device, attack, IP, service..."
                class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90"
            />
            <select name="severity" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua severity</option>
                @foreach ($severities as $severity)
                    <option value="{{ $severity }}" @selected($filters['severity'] === $severity)>{{ $severity }}</option>
                @endforeach
            </select>
            <div class="flex flex-wrap gap-2">
                <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Filter</button>
                <a href="{{ route('admin.log-security.index') }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/10">Reset</a>
            </div>
        </form>

        <div class="min-h-0 flex-1 overflow-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="min-w-[1400px] w-full border-separate border-spacing-0 text-left">
                <thead class="[&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:border-b [&_th]:border-gray-200 [&_th]:bg-gray-50 dark:[&_th]:border-gray-800 dark:[&_th]:bg-slate-900">
                    <tr class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="whitespace-nowrap px-3 py-3">Waktu</th>
                        <th class="px-3 py-3">Device</th>
                        <th class="px-3 py-3">Severity</th>
                        <th class="px-3 py-3">Attack</th>
                        <th class="px-3 py-3">Type / Action</th>
                        <th class="px-3 py-3">Source</th>
                        <th class="px-3 py-3">Destination</th>
                        <th class="px-3 py-3">HTTP / URL</th>
                        <th class="whitespace-nowrap px-3 py-3">Diterima</th>
                        <th class="px-3 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="align-top [&_td]:border-b [&_td]:border-gray-100 dark:[&_td]:border-gray-800">
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-gray-700 dark:text-gray-300">{{ $log->event_time }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm font-medium text-gray-900 dark:text-white/90">{{ $log->device }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs">
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ $log->severity }}</span>
                            </td>
                            <td class="max-w-72 px-3 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div>{{ $log->attack }} (ID {{ $log->attackid }})</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $log->subtype }}<br>{{ $log->action }}</td>
                            <td class="whitespace-nowrap px-3 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $log->srcip }}:{{ $log->srcport }}</td>
                            <td class="whitespace-nowrap px-3 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                {{ $log->dstip }}:{{ $log->dstport }}<br>
                                <span class="font-sans">{{ $log->service }}</span>
                            </td>
                            <td class="max-w-80 break-all px-3 py-3 text-xs text-gray-700 dark:text-gray-300">
                                @if ($log->method !== '-')
                                    <span>{{ $log->method }}</span>
                                @endif
                                {{ $log->url }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <form method="POST" action="{{ route('admin.log-security.destroy', $log) }}" onsubmit="return confirm('Hapus log serangan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-900/50 dark:text-red-300 dark:hover:bg-red-900/20">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada log serangan yang sesuai filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="mt-4">{{ $logs->links() }}</div>
        @endif
    </x-dashboard.accent-card>
@endsection
