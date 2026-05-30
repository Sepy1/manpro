@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Aset TI - Monitoring" />
    <x-dashboard.accent-card
        accent-index="0"
        shell-overflow="visible"
        class="flex min-h-0 h-full flex-col"
        padding="p-5 lg:p-6"
    >
        <div
            class="flex min-h-0 flex-1 flex-col"
            x-data="{
        rows: @js($rows),
        errorMessage: @js($errorMessage),
        lastUpdatedAt: @js($lastUpdatedAt->format('d M Y H:i:s')),
        dataUrl: @js(route('admin.aset-ti.monitoring.data')),
        tableBodyAvailablePx: 0,
        intervalMs: 300000,
        intervalId: null,
        init() {
            this.startAutoRefresh();
            this.$nextTick(() => {
                this.setupLayoutAutoFit();
            });
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
                this.$nextTick(() => this.recalculateLayout());
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
        usageBarClass(percent) {
            if (percent >= 85) return 'bg-red-500';
            if (percent >= 70) return 'bg-amber-500';
            return 'bg-emerald-500';
        },
        rowCount() {
            return Array.isArray(this.rows) ? this.rows.length : 0;
        },
        setupLayoutAutoFit() {
            this.recalculateLayout();
            const recalc = () => this.recalculateLayout();
            window.addEventListener('resize', recalc);
            if (typeof ResizeObserver === 'function') {
                this._resizeObserver = new ResizeObserver(() => this.recalculateLayout());
                if (this.$refs.tableViewport) this._resizeObserver.observe(this.$refs.tableViewport);
                if (this.$refs.tableHead) this._resizeObserver.observe(this.$refs.tableHead);
            }
        },
        recalculateLayout() {
            const viewport = this.$refs.tableViewport;
            const thead = this.$refs.tableHead;
            if (!viewport) return;
            const viewportHeight = viewport.clientHeight || 0;
            const headHeight = thead ? thead.offsetHeight : 0;
            this.tableBodyAvailablePx = Math.max(viewportHeight - headHeight - 4, 0);
        },
        tableScale() {
            const count = this.rowCount();
            if (count <= 10) return 1;
            if (count <= 14) return 0.93;
            if (count <= 18) return 0.86;
            if (count <= 22) return 0.8;
            if (count <= 26) return 0.74;
            return 0.68;
        },
        tableStyle() {
            const scale = this.tableScale();
            const width = 100 / scale;
            return `transform: scale(${scale}); transform-origin: top left; width: ${width}%;`;
        },
        rowCellClass() {
            const count = this.rowCount();
            if (count > 22) return 'px-2 py-1.5 text-[11px]';
            if (count > 16) return 'px-2 py-2 text-xs';
            return 'px-2.5 py-2.5 text-[13px]';
        },
        headCellClass() {
            const count = this.rowCount();
            if (count > 22) return 'px-2 py-2 text-[11px]';
            if (count > 16) return 'px-2 py-2 text-[11px]';
            return 'px-2.5 py-2.5 text-xs';
        },
        barHeightClass() {
            const count = this.rowCount();
            if (count > 22) return 'h-1.5';
            if (count > 16) return 'h-2';
            return 'h-2.5';
        },
        rowHeightStyle() {
            const count = this.rowCount();
            if (count < 1 || count > 14 || this.tableBodyAvailablePx <= 0) {
                return '';
            }
            const rowHeight = Math.max(Math.floor(this.tableBodyAvailablePx / count), 38);
            return `height: ${rowHeight}px;`;
        },
    }">
        <div class="flex min-h-0 flex-1 flex-col">
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

        <div class="min-h-0 flex-1 overflow-hidden" x-ref="tableViewport">
            <div class="h-full w-full" :style="tableStyle()">
            <table class="w-full table-fixed border-separate border-spacing-0">
                <thead x-ref="tableHead" class="[&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:border-b [&_th]:border-gray-200 [&_th]:bg-white dark:[&_th]:border-gray-800 dark:[&_th]:bg-slate-900">
                    <tr>
                        <th class="w-[24%] text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" :class="headCellClass()">Server</th>
                        <th class="w-[14%] text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" :class="headCellClass()">Status</th>
                        <th class="w-[14%] text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" :class="headCellClass()">Cpu Load</th>
                        <th class="w-[16%] text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" :class="headCellClass()">Load RAM</th>
                        <th class="w-[16%] text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" :class="headCellClass()">Traffic</th>
                        <th class="w-[16%] text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" :class="headCellClass()">Disk Usage</th>
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
                        <tr class="border-b border-gray-100 dark:border-gray-800" :style="rowHeightStyle()">
                            <td class="truncate font-medium text-gray-800 dark:text-white/90" :class="rowCellClass()" :title="row.server" x-text="row.server"></td>
                            <td class="truncate font-semibold" :class="`${rowCellClass()} ${statusClass(row.status)}`" x-text="row.status"></td>
                            <td class="text-gray-700 dark:text-gray-300" :class="rowCellClass()">
                                <div class="mb-1 truncate" x-text="row.cpu_load"></div>
                                <template x-if="extractPercent(row.cpu_load) !== null">
                                    <div class="w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" :class="barHeightClass()">
                                        <div class="h-full" :class="cpuBarClass(extractPercent(row.cpu_load))" :style="`width: ${extractPercent(row.cpu_load)}%`"></div>
                                    </div>
                                </template>
                            </td>
                            <td class="text-gray-700 dark:text-gray-300" :class="rowCellClass()">
                                <div class="mb-1 truncate" x-text="row.ram_load"></div>
                                <template x-if="extractPercent(row.ram_load) !== null">
                                    <div class="w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" :class="barHeightClass()">
                                        <div class="h-full" :class="usageBarClass(extractPercent(row.ram_load))" :style="`width: ${extractPercent(row.ram_load)}%`"></div>
                                    </div>
                                </template>
                            </td>
                            <td class="truncate text-gray-700 dark:text-gray-300" :class="rowCellClass()" x-text="row.traffic"></td>
                            <td class="text-gray-700 dark:text-gray-300" :class="rowCellClass()">
                                <div class="mb-1 truncate" x-text="row.disk_usage"></div>
                                <template x-if="extractPercent(row.disk_usage) !== null">
                                    <div class="w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" :class="barHeightClass()">
                                        <div class="h-full" :class="usageBarClass(extractPercent(row.disk_usage))" :style="`width: ${extractPercent(row.disk_usage)}%`"></div>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            </div>
        </div>
        </div>
        </div>
    </x-dashboard.accent-card>
@endsection
