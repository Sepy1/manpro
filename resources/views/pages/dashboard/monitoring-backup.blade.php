@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Monitoring Backup" />

    <x-dashboard.accent-card
        accent-index="0"
        shell-overflow="visible"
        class="flex min-h-0 h-full flex-col"
        padding="p-5 lg:p-6"
    >
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Riwayat Backup Database</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Menampilkan {{ number_format($filteredCount) }} dari {{ number_format($totalCount) }} event backup.
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="GET" action="{{ route('admin.monitoring-backup.index') }}" class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
            <input
                type="text"
                name="keyword"
                value="{{ $filters['keyword'] }}"
                placeholder="Cari server, database, lokasi file..."
                class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90"
            />
            <select name="status" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <span class="shrink-0">Dari</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                    class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white/90" />
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <span class="shrink-0">Sampai</span>
                <input type="date" name="date_until" value="{{ $filters['date_until'] }}"
                    class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white/90" />
            </label>
            <div class="flex gap-2">
                <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Filter</button>
                <a href="{{ route('admin.monitoring-backup.index') }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/10">Reset</a>
            </div>
        </form>

        <div class="min-h-0 flex-1 overflow-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="min-w-[900px] w-full border-separate border-spacing-0 text-left">
                <thead class="[&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:border-b [&_th]:border-gray-200 [&_th]:bg-gray-50 dark:[&_th]:border-gray-800 dark:[&_th]:bg-slate-900">
                    <tr class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="whitespace-nowrap px-3 py-3">Tanggal Backup</th>
                        <th class="px-3 py-3">Server</th>
                        <th class="px-3 py-3">Database</th>
                        <th class="whitespace-nowrap px-3 py-3 text-right">Ukuran</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Lokasi File</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        @php($statusClass = match (strtoupper($event->status)) {
                            'SUCCESS', 'SUCCEEDED', 'COMPLETED' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                            'FAILED', 'FAILURE', 'ERROR' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                            default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                        })
                        <tr class="align-top [&_td]:border-b [&_td]:border-gray-100 dark:[&_td]:border-gray-800">
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-gray-700 dark:text-gray-300">{{ $event->tanggal->format('Y-m-d H:i:s') }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm font-medium text-gray-900 dark:text-white/90">{{ $event->server }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $event->nama_db }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ number_format((float) $event->ukuran, 2, '.', ',') }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-xs">
                                <span class="inline-flex rounded-full px-2 py-1 font-medium {{ $statusClass }}">{{ $event->status }}</span>
                            </td>
                            <td class="min-w-64 break-all px-3 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $event->lokasi_file }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada event backup yang sesuai filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($events->hasPages())
            <div class="mt-4">{{ $events->links() }}</div>
        @endif
    </x-dashboard.accent-card>
@endsection
