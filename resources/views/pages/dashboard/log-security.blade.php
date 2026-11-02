@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Log Security" />

    <x-dashboard.accent-card
        accent-index="0"
        shell-overflow="visible"
        class="flex min-h-0 min-w-0 h-full flex-col"
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

        <form method="GET" action="{{ route('admin.log-security.index') }}" class="mb-5 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-700">
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
            <select name="device" class="h-10 min-w-0 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua device</option>
                @foreach ($devices as $device)
                    <option value="{{ $device }}" @selected($filters['device'] === $device)>{{ $device }}</option>
                @endforeach
            </select>
            <select name="action" class="h-10 min-w-0 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua action</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $action }}</option>
                @endforeach
            </select>
            <input
                type="text"
                name="source"
                value="{{ $filters['source'] }}"
                placeholder="Source IP"
                class="h-10 min-w-0 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90"
            />
            <input
                type="text"
                name="destination"
                value="{{ $filters['destination'] }}"
                placeholder="Destination IP"
                class="h-10 min-w-0 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90"
            />
            <label class="flex min-w-0 items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <span class="shrink-0">Dari</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-2 dark:border-gray-700 dark:text-white/90" />
            </label>
            <label class="flex min-w-0 items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <span class="shrink-0">Sampai</span>
                <input type="date" name="date_until" value="{{ $filters['date_until'] }}" class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-2 dark:border-gray-700 dark:text-white/90" />
            </label>
            <div class="flex flex-wrap gap-2 sm:col-span-2 xl:col-span-4">
                <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Filter</button>
                <a href="{{ route('admin.log-security.export', request()->query()) }}" class="inline-flex h-10 items-center rounded-lg border border-green-600 px-4 text-sm font-medium text-green-700 hover:bg-green-50 dark:text-green-300 dark:hover:bg-green-900/20">Export Excel</a>
                <a href="{{ route('admin.log-security.index') }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/10">Reset</a>
            </div>
        </form>

        <div class="w-full min-h-0 min-w-0 max-w-full flex-1 overflow-x-hidden overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="w-full min-w-0 table-fixed border-separate border-spacing-0 text-left">
                <thead class="[&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:border-b [&_th]:border-gray-200 [&_th]:bg-gray-50 dark:[&_th]:border-gray-800 dark:[&_th]:bg-slate-900">
                    <tr class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="w-[14%] px-2 py-3">Waktu</th>
                        <th class="w-[14%] px-2 py-3">Device / Severity</th>
                        <th class="w-[19%] px-2 py-3">Serangan</th>
                        <th class="w-[14%] px-2 py-3">Source</th>
                        <th class="w-[14%] px-2 py-3">Destination</th>
                        <th class="w-[18%] px-2 py-3">Request</th>
                        <th class="w-[7%] px-2 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="align-top [&_td]:border-b [&_td]:border-gray-100 dark:[&_td]:border-gray-800">
                            <td class="break-words px-2 py-3 text-xs text-gray-700 dark:text-gray-300">
                                <div>{{ $log->event_time }}</div>
                                <div class="mt-1 text-[11px] text-gray-400">Masuk {{ $log->created_at->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="min-w-0 px-2 py-3 text-sm font-medium text-gray-900 dark:text-white/90">
                                <div class="truncate" title="{{ $log->device }}">{{ $log->device }}</div>
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ $log->severity }}</span>
                            </td>
                            <td class="break-words px-2 py-3 text-sm text-gray-700 dark:text-gray-300">
                                <div class="font-medium text-gray-900 dark:text-white/90">{{ $log->attack }}</div>
                                <div class="mt-1 text-xs text-gray-500">ID {{ $log->attackid }} · {{ $log->subtype }} / {{ $log->action }}</div>
                            </td>
                            <td class="break-all px-2 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $log->srcip }}:{{ $log->srcport }}</td>
                            <td class="break-all px-2 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">
                                {{ $log->dstip }}:{{ $log->dstport }}<br>
                                <span class="font-sans">{{ $log->service }}</span>
                            </td>
                            <td class="break-all px-2 py-3 text-xs text-gray-700 dark:text-gray-300">
                                @if ($log->method !== '-')
                                    <span>{{ $log->method }}</span>
                                @endif
                                {{ $log->url }}
                            </td>
                            <td class="px-1 py-3 text-center">
                                <form method="POST" action="{{ route('admin.log-security.destroy', $log) }}" onsubmit="return confirm('Hapus log serangan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="max-w-full rounded-lg border border-red-300 px-2 py-1.5 text-[11px] font-medium text-red-700 hover:bg-red-50 dark:border-red-900/50 dark:text-red-300 dark:hover:bg-red-900/20">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
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
