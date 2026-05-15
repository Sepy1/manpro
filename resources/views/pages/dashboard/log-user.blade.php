@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Log User" />

    <div class="flex min-h-0 h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Log Aktivitas User</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan log login dan aktivitas membuka menu.</p>
        </div>

            <form method="GET" action="{{ route('admin.manajemen-log-user.index') }}"
                class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 md:grid-cols-6 dark:border-gray-700">
                <select name="user_id"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                    <option value="">Semua User</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($filters['user_id'] === (string) $user->id)>
                            {{ $user->name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>

                <select name="activity_type"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                    <option value="">Semua Aktivitas</option>
                    @foreach ($activityTypes as $value => $label)
                        <option value="{{ $value }}" @selected($filters['activity_type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="date" name="period_start" value="{{ $filters['period_start'] }}"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />

                <input type="date" name="period_end" value="{{ $filters['period_end'] }}"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />

                <input type="text" name="keyword" value="{{ $filters['keyword'] }}" placeholder="Cari user, route, IP, URL"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />

                <div class="flex items-center gap-2">
                    <button type="submit"
                        class="inline-flex h-10 items-center rounded-lg border border-brand-500 px-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                        Filter
                    </button>
                    <a href="{{ route('admin.manajemen-log-user.index') }}"
                        class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/10">
                        Reset
                    </a>
                </div>
            </form>

            <div class="min-h-0 flex-1 overflow-auto">
                <table class="min-w-full table-fixed border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Waktu</th>
                            <th class="w-[18%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">User</th>
                            <th class="w-[8%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Role</th>
                            <th class="w-[10%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aktivitas</th>
                            <th class="w-[14%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Menu</th>
                            <th class="w-[16%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Route</th>
                            <th class="w-[10%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">IP</th>
                            <th class="w-[12%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">
                                    {{ optional($log->created_at)->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">
                                    <div class="font-medium">{{ $log->user?->name ?? '-' }}</div>
                                    <div class="text-gray-500 dark:text-gray-400">{{ $log->user?->email ?? '-' }}</div>
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">
                                    {{ ucfirst($log->user?->role ?? '-') }}
                                </td>
                                <td class="px-3 py-3 text-xs">
                                    @if ($log->activity_type === 'login')
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Login</span>
                                    @elseif ($log->activity_type === 'login_failed')
                                        <span class="inline-flex rounded-full bg-rose-100 px-2 py-0.5 font-medium text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Login Gagal</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">Buka Menu</span>
                                    @endif
                                    @if ($log->activity_type === 'login_failed' && $log->failure_reason)
                                        <div class="mt-1 text-[11px] text-rose-600 dark:text-rose-300">{{ $log->failure_reason }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">
                                    {{ $log->menu_name ?: '-' }}
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">
                                    <div>{{ $log->route_name ?: '-' }}</div>
                                    @if ($log->attempted_email)
                                        <div class="text-gray-500 dark:text-gray-400">Email: {{ $log->attempted_email }}</div>
                                    @endif
                                    @if ($log->url)
                                        <div class="text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($log->url, 60) }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">{{ $log->ip_address ?: '-' }}</td>
                                <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">{{ $log->method ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data log.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        <div class="mt-4 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
            Total data: {{ $logs->count() }}
        </div>
    </div>
@endsection
