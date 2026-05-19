@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Aset TI - Monitoring" />
    <div x-data="{
        rows: @js($rows),
        errorMessage: @js($errorMessage),
        lastUpdatedAt: @js($lastUpdatedAt->format('d M Y H:i:s')),
        dataUrl: @js(route('admin.aset-ti.monitoring.data')),
        intervalMs: 300000,
        intervalId: null,
        init() {
            this.startAutoRefresh();
        },
        async refreshData() {
            try {
                const response = await fetch(this.dataUrl, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('Failed to fetch monitoring data.');
                }

                const payload = await response.json();
                this.rows = Array.isArray(payload.rows) ? payload.rows : [];
                this.errorMessage = payload.errorMessage || null;
                this.lastUpdatedAt = payload.lastUpdatedAt || this.lastUpdatedAt;
            } catch (error) {
                this.errorMessage = 'Gagal refresh otomatis data monitoring. Data terakhir tetap ditampilkan.';
            }
        },
        startAutoRefresh() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
            }
            this.intervalId = setInterval(() => this.refreshData(), this.intervalMs);
        },
        statusClass(status) {
            const text = String(status || '').toLowerCase();
            if (text.includes('down')) return 'text-red-600 dark:text-red-300';
            if (text.includes('warning') || text.includes('unusual')) return 'text-amber-600 dark:text-amber-300';
            if (text.includes('up')) return 'text-emerald-600 dark:text-emerald-300';
            return 'text-gray-700 dark:text-gray-300';
        },
        extractPercent(value) {
            const text = String(value || '').trim();
            if (!text.includes('%')) return null;
            const match = text.match(/-?\d+(?:[.,]\d+)?/);
            if (!match) return null;
            const parsed = Number(match[0].replace(',', '.'));
            if (Number.isNaN(parsed)) return null;
            return Math.max(0, Math.min(100, parsed));
        },
        cpuBarClass(percent) {
            if (percent >= 85) return 'bg-red-500';
            if (percent >= 70) return 'bg-amber-500';
            return 'bg-emerald-500';
        },
        freeBarClass(percent) {
            if (percent < 30) return 'bg-red-500';
            if (percent < 50) return 'bg-amber-500';
            return 'bg-emerald-500';
        },
    }" class="flex min-h-0 h-full flex-col content-card p-5 lg:p-6">
        <div class="mb-4 flex items-center justify-between gap-2">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Monitoring Statistik Server (PRTG)</h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                Last update: <span x-text="lastUpdatedAt"></span>
            </span>
        </div>

        <div x-show="errorMessage" x-cloak class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
            <span x-text="errorMessage"></span>
        </div>

        <div class="mb-3 flex justify-end">
            <button type="button" @click="refreshData()"
                class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                Refresh Sekarang
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-auto">
            <table class="w-full table-fixed border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="w-[24%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Server</th>
                        <th class="w-[14%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                        <th class="w-[14%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cpu Load</th>
                        <th class="w-[16%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Free RAM</th>
                        <th class="w-[16%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Traffic</th>
                        <th class="w-[16%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Disk Free</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="rows.length === 0">
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Belum ada data monitoring dari PRTG.
                            </td>
                        </tr>
                    </template>

                    <template x-for="row in rows" :key="row.server">
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="truncate px-2 py-2 text-xs font-medium text-gray-800 dark:text-white/90" :title="row.server" x-text="row.server"></td>
                            <td class="truncate px-2 py-2 text-xs font-semibold" :class="statusClass(row.status)" x-text="row.status"></td>
                            <td class="px-2 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <div class="mb-1 truncate" x-text="row.cpu_load"></div>
                                <template x-if="extractPercent(row.cpu_load) !== null">
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div class="h-full" :class="cpuBarClass(extractPercent(row.cpu_load))" :style="`width: ${extractPercent(row.cpu_load)}%`"></div>
                                    </div>
                                </template>
                            </td>
                            <td class="px-2 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <div class="mb-1 truncate" x-text="row.free_ram"></div>
                                <template x-if="extractPercent(row.free_ram) !== null">
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div class="h-full" :class="freeBarClass(extractPercent(row.free_ram))" :style="`width: ${extractPercent(row.free_ram)}%`"></div>
                                    </div>
                                </template>
                            </td>
                            <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" x-text="row.traffic"></td>
                            <td class="px-2 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <div class="mb-1 truncate" x-text="row.disk_free"></div>
                                <template x-if="extractPercent(row.disk_free) !== null">
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div class="h-full" :class="freeBarClass(extractPercent(row.disk_free))" :style="`width: ${extractPercent(row.disk_free)}%`"></div>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
@endsection
