@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Parameter Monitoring Jaringan" />

    <div class="mx-auto w-full max-w-3xl">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <x-dashboard.accent-card accent-index="1" padding="p-5 lg:p-6">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Polling Ping Kantor</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Atur seberapa sering server menguji IP wireless seluruh kantor aktif. Scheduler Laravel harus berjalan agar polling otomatis aktif.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.parameter.network-monitor.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="polling_interval_minutes" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Interval polling ping
                    </label>
                    <div class="flex max-w-sm items-center gap-3">
                        <input id="polling_interval_minutes" name="polling_interval_minutes" type="number"
                            min="{{ \App\Models\NetworkMonitorSetting::MIN_INTERVAL_MINUTES }}"
                            max="{{ \App\Models\NetworkMonitorSetting::MAX_INTERVAL_MINUTES }}"
                            value="{{ old('polling_interval_minutes', $intervalMinutes) }}" required
                            class="h-11 w-32 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white" />
                        <span class="text-sm text-gray-600 dark:text-gray-300">menit</span>
                    </div>
                    @error('polling_interval_minutes')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Rentang 1–1.440 menit. Nilai umum yang disarankan: 5 menit.</p>
                </div>

                <div>
                    <label for="high_ping_threshold_ms" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Ambang high ping
                    </label>
                    <div class="flex max-w-sm items-center gap-3">
                        <input id="high_ping_threshold_ms" name="high_ping_threshold_ms" type="number"
                            min="{{ \App\Models\NetworkMonitorSetting::MIN_HIGH_PING_THRESHOLD_MS }}"
                            max="{{ \App\Models\NetworkMonitorSetting::MAX_HIGH_PING_THRESHOLD_MS }}"
                            value="{{ old('high_ping_threshold_ms', $highPingThresholdMs) }}" required
                            class="h-11 w-32 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white" />
                        <span class="text-sm text-gray-600 dark:text-gray-300">ms</span>
                    </div>
                    @error('high_ping_threshold_ms')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Kantor dengan reply di atas nilai ini ditandai kuning. Default: 50 ms.</p>
                </div>

                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">
                    Simpan parameter
                </button>
            </form>
        </x-dashboard.accent-card>
    </div>
@endsection
