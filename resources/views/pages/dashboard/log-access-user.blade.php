@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Log Akses User" />

    <x-dashboard.accent-card accent-index="0" shell-overflow="visible" class="flex min-h-0 min-w-0 h-full flex-col" padding="p-5 lg:p-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Aktivitas Login Perangkat</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ number_format($filteredCount) }} dari {{ number_format($totalCount) }} log.</p>
            </div>
            <a href="{{ route('admin.log-access-user.export', request()->query()) }}"
                class="inline-flex h-10 items-center rounded-lg border border-green-600 px-4 text-sm font-medium text-green-700 transition hover:bg-green-50 dark:text-green-300 dark:hover:bg-green-900/20">
                Export Excel
            </a>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">{{ $errors->first() }}</div>
        @endif

        <form method="GET" action="{{ route('admin.log-access-user.index') }}" class="mb-5 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-700">
            <input name="keyword" value="{{ $filters['keyword'] }}" placeholder="Cari user, IP, perangkat, pesan..." class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
            <select name="status" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>@endforeach
            </select>
            <select name="device" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua perangkat</option>
                @foreach ($devices as $device)<option value="{{ $device }}" @selected($filters['device'] === $device)>{{ $device }}</option>@endforeach
            </select>
            <div class="hidden xl:block"></div>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">Dari <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-2 dark:border-gray-700" /></label>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">Sampai <input type="date" name="date_until" value="{{ $filters['date_until'] }}" class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-2 dark:border-gray-700" /></label>
            <div class="flex gap-2 sm:col-span-2">
                <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Filter</button>
                <a href="{{ route('admin.log-access-user.index') }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300">Reset</a>
            </div>
        </form>

        <div class="w-full min-h-0 flex-1 overflow-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="w-full min-w-[1050px] border-separate border-spacing-0 text-left">
                <thead class="[&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:border-b [&_th]:border-gray-200 [&_th]:bg-gray-50 dark:[&_th]:border-gray-800 dark:[&_th]:bg-slate-900">
                    <tr class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-3">Waktu</th><th class="px-3 py-3">User / Status</th><th class="px-3 py-3">Perangkat</th><th class="px-3 py-3">Sumber</th><th class="px-3 py-3">Aktivitas</th><th class="px-3 py-3">Pesan</th><th class="px-3 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="align-top [&_td]:border-b [&_td]:border-gray-100 dark:[&_td]:border-gray-800">
                            <td class="whitespace-nowrap px-3 py-3 text-xs text-gray-700 dark:text-gray-300">{{ $log->event_date?->format('d/m/Y') ?? '-' }}<br>{{ $log->event_clock ?? '-' }} {{ $log->timezone }}</td>
                            <td class="px-3 py-3 text-sm"><div class="font-semibold text-gray-900 dark:text-white">{{ $log->username }}</div><span @class(['mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' => $log->status === 'success', 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' => $log->status !== 'success'])>{{ $log->status }}</span></td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300"><div class="font-medium">{{ $log->device_name }}</div><div class="text-gray-500">{{ $log->device_id }} · {{ $log->vendor }}</div></td>
                            <td class="px-3 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $log->source_ip }}<br><span class="font-sans text-gray-500">{{ $log->source_interface }}</span></td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300"><div>{{ $log->method }} / {{ $log->action }}</div><div class="text-gray-500">{{ $log->event_type }}</div></td>
                            <td class="max-w-sm px-3 py-3 text-xs text-gray-700 dark:text-gray-300">
                                <div>{{ $log->message }}</div>
                                <details class="mt-2"><summary class="cursor-pointer text-brand-600 dark:text-brand-400">Detail lengkap</summary><dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-2 gap-y-1 rounded-lg bg-gray-50 p-3 dark:bg-white/5">@foreach ($log->getAttributes() as $key => $value)<dt class="font-medium">{{ $key }}</dt><dd class="break-all">{{ $value ?? '-' }}</dd>@endforeach</dl></details>
                            </td>
                            <td class="px-3 py-3 text-center"><form method="POST" action="{{ route('admin.log-access-user.destroy', $log) }}" onsubmit="return confirm('Hapus log akses ini?')">@csrf @method('DELETE')<button class="rounded-lg border border-red-300 px-2 py-1.5 text-xs text-red-700 hover:bg-red-50 dark:text-red-300">Hapus</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">Tidak ada log akses user yang sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())<div class="mt-4">{{ $logs->links() }}</div>@endif
    </x-dashboard.accent-card>
@endsection
