@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Aset TI - Statistik Server" />

    <div x-data="{
        initialRows: @js($rows),
        rows: [],
        selectedStartDate: '',
        selectedEndDate: '',
        hasSubmitted: false,
        queueLoading: false,
        errorMessage: null,
        startDateLabel: '-',
        endDateLabel: '-',
        sensorUrlTemplate: @js($sensorUrlTemplate),
        createMetricState(available) {
            return {
                available,
                loading: false,
                loaded: !available,
                requested: false,
                error: null,
                stats: { min: null, max: null, avg: null },
                bars: { min: null, max: null, avg: null },
                uptime_percent: null,
                stats_free_gb: null,
            };
        },
        async init() {
            this.rows = this.initialRows.map((row) => ({
                id: row.id,
                server: row.server,
                metrics: {
                    ping: this.createMetricState(row.availability.ping),
                    cpu: this.createMetricState(row.availability.cpu),
                    ram: this.createMetricState(row.availability.ram),
                    traffic: this.createMetricState(row.availability.traffic),
                    disk: this.createMetricState(row.availability.disk),
                },
            }));
        },
        buildSensorUrl(deviceId, metric) {
            const base = this.sensorUrlTemplate
                .replace('__DEVICE__', String(deviceId))
                .replace('__METRIC__', metric);

            const query = new URLSearchParams({
                start_date: this.selectedStartDate,
                end_date: this.selectedEndDate,
            });

            return `${base}?${query.toString()}`;
        },
        resetMetricStates() {
            this.rows.forEach((row) => {
                ['ping', 'cpu', 'ram', 'traffic', 'disk'].forEach((metric) => {
                    const state = row.metrics[metric];
                    state.loading = false;
                    state.loaded = !state.available;
                    state.requested = false;
                    state.error = null;
                    state.stats = { min: null, max: null, avg: null };
                    state.bars = { min: null, max: null, avg: null };
                    state.uptime_percent = null;
                    state.stats_free_gb = null;
                });
            });
        },
        async submitPeriod() {
            this.errorMessage = null;

            if (!this.selectedStartDate || !this.selectedEndDate) {
                this.errorMessage = 'Silakan pilih periode terlebih dahulu.';
                return;
            }

            if (this.selectedStartDate > this.selectedEndDate) {
                this.errorMessage = 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.';
                return;
            }

            this.startDateLabel = this.selectedStartDate;
            this.endDateLabel = this.selectedEndDate;
            this.hasSubmitted = true;
            this.resetMetricStates();
            await this.loadAllSensorsSequentially();
        },
        async loadAllSensorsSequentially() {
            this.queueLoading = true;

            const queue = [];
            this.rows.forEach((row) => {
                ['ping', 'cpu', 'ram', 'traffic', 'disk'].forEach((metric) => {
                    if (row.metrics[metric].available) {
                        queue.push({ row, metric });
                    }
                });
            });

            const workerCount = Math.min(4, Math.max(1, queue.length));
            let pointer = 0;

            const runWorker = async () => {
                while (pointer < queue.length) {
                    const currentIndex = pointer;
                    pointer += 1;
                    const task = queue[currentIndex];
                    await this.loadSensor(task.row, task.metric);
                }
            };

            const workers = Array.from({ length: workerCount }, () => runWorker());
            await Promise.all(workers);

            if (queue.length === 0) {
                this.queueLoading = false;
                return;
            }
            this.queueLoading = false;
        },
        async loadSensor(row, metric) {
            const state = row.metrics[metric];
            if (!state.available) return;

            state.requested = true;
            state.loading = true;
            state.error = null;

            try {
                const response = await fetch(this.buildSensorUrl(row.id, metric), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const payload = await response.json();

                if (!response.ok || !payload.ok) {
                    throw new Error(payload.message || 'Sensor request failed.');
                }

                if (metric === 'ping') {
                    state.uptime_percent = payload.uptime_percent;
                } else {
                    if (metric === 'ram') {
                        const rawFree = payload.stats || { min: null, max: null, avg: null };
                        state.stats = this.freeRamStatsToLoadStats(rawFree);
                        state.bars = state.stats;
                    } else {
                        state.stats = payload.stats || { min: null, max: null, avg: null };
                        state.bars = payload.bars || state.stats;
                    }
                    if (metric === 'disk') {
                        state.stats_free_gb = payload.stats_free_gb || null;
                    }
                }
            } catch (error) {
                state.error = 'Gagal';
                this.errorMessage = 'Sebagian sensor gagal diambil dari API PRTG.';
            } finally {
                state.loading = false;
                state.loaded = true;
            }
        },
        hasLoadedData() {
            return this.rows.length > 0;
        },
        spinnerHtml() {
            return `<span class='inline-block h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500'></span>`;
        },
        /** API PRTG: min/max/avg = persen RAM bebas → tampilan load = 100 − nilai. */
        freeRamStatsToLoadStats(freeStats) {
            if (!freeStats) return { min: null, max: null, avg: null };
            const n = (v) => (v !== null && v !== undefined && !Number.isNaN(Number(v)) ? Number(v) : null);
            const fmin = n(freeStats.min);
            const fmax = n(freeStats.max);
            const favg = n(freeStats.avg);
            const r2 = (x) => Math.round(x * 100) / 100;
            return {
                min: fmax !== null ? r2(100 - fmax) : null,
                max: fmin !== null ? r2(100 - fmin) : null,
                avg: favg !== null ? r2(100 - favg) : null,
            };
        },
        formatPercent(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) return '-';
            return `${Number(value).toFixed(2)}%`;
        },
        formatTraffic(bytesPerSec) {
            if (bytesPerSec === null || bytesPerSec === undefined || Number.isNaN(Number(bytesPerSec))) return '-';
            const bps = Number(bytesPerSec) * 8;
            if (bps >= 1000000000) return `${(bps / 1000000000).toFixed(2)} Gbit/s`;
            if (bps >= 1000000) return `${(bps / 1000000).toFixed(2)} Mbit/s`;
            if (bps >= 1000) return `${(bps / 1000).toFixed(2)} Kbit/s`;
            return `${bps.toFixed(2)} bit/s`;
        },
        uptimeBarClass(percent) {
            if (percent === null || percent === undefined) return 'bg-gray-300';
            if (percent < 90) return 'bg-red-500';
            if (percent < 98) return 'bg-amber-500';
            return 'bg-emerald-500';
        },
        cpuBarClass(percent) {
            if (percent === null || percent === undefined) return 'bg-gray-300';
            if (percent >= 85) return 'bg-red-500';
            if (percent >= 70) return 'bg-amber-500';
            return 'bg-emerald-500';
        },
        ramBarClass(percent) {
            if (percent === null || percent === undefined) return 'bg-gray-300';
            if (percent < 30) return 'bg-red-500';
            if (percent < 50) return 'bg-amber-500';
            return 'bg-emerald-500';
        },
        barWidth(percent) {
            if (percent === null || percent === undefined || Number.isNaN(Number(percent))) return 0;
            return Math.max(0, Math.min(100, Number(percent)));
        },
        metricBarClass(kind, percent) {
            if (kind === 'cpu' || kind === 'ram_load') return this.cpuBarClass(percent);
            if (kind === 'ram' || kind === 'disk') return this.ramBarClass(percent);
            return 'bg-brand-500';
        },
        diskStatRowLabel(metricKey) {
            const labels = { min: 'Awal', max: 'Akhir', avg: 'Selisih' };
            return labels[metricKey] || metricKey;
        },
        formatDiskDeltaPct(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) return '-';
            const n = Number(value);
            if (Object.is(n, -0) || n === 0) return `${n.toFixed(2)}%`;
            const sign = n > 0 ? '+' : '';
            return `${sign}${n.toFixed(2)}%`;
        },
        diskDeltaBarClass(delta) {
            if (delta === null || delta === undefined || Number.isNaN(Number(delta))) return 'bg-gray-300';
            if (Number(delta) >= 0) return 'bg-emerald-500';
            return 'bg-red-500';
        },
        diskDeltaBarWidth(delta) {
            if (delta === null || delta === undefined || Number.isNaN(Number(delta))) return 0;
            return Math.min(100, Math.abs(Number(delta)) * 5);
        },
        diskFreeRowDisplay(metricKey, row) {
            const s = row.metrics.disk.stats;
            const pct = metricKey === 'avg'
                ? this.formatDiskDeltaPct(s.avg)
                : this.formatPercent(s[metricKey]);
            const g = row.metrics.disk.stats_free_gb;
            if (!g || g[metricKey] === null || g[metricKey] === undefined || Number.isNaN(Number(g[metricKey]))) {
                return pct;
            }
            const n = Number(g[metricKey]);
            const gbSuffix = metricKey === 'avg'
                ? (n === 0 ? ' · 0.00 GB' : (' · ' + (n > 0 ? '+' : '') + n.toFixed(2) + ' GB'))
                : (' · ' + n.toFixed(2) + ' GB');
            return pct + gbSuffix;
        },
    }" class="flex min-h-0 h-full flex-col content-card p-5 lg:p-6">
        <div class="mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Statistik Server Bulan Berjalan (PRTG)</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="hasSubmitted">
                Periode: <span x-text="startDateLabel"></span> - <span x-text="endDateLabel"></span>
            </p>
        </div>

        <form class="mb-4 grid gap-3 md:grid-cols-4" @submit.prevent="submitPeriod()">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Tanggal Mulai</label>
                <input type="date" x-model="selectedStartDate"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Tanggal Akhir</label>
                <input type="date" x-model="selectedEndDate"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
            </div>
            <div class="md:col-span-2 flex items-end">
                <button type="submit"
                    class="inline-flex h-10 items-center rounded-lg border border-brand-500 px-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                    Tampilkan Statistik
                </button>
            </div>
        </form>

        <div x-show="queueLoading" x-cloak class="mb-3 text-xs text-gray-500 dark:text-gray-400">
            Memuat data per sensor...
        </div>

        <div x-show="errorMessage" x-cloak class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
            <span x-text="errorMessage"></span>
        </div>

        <div class="min-h-0 flex-1 overflow-auto">
            <table class="w-full table-fixed border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="w-[16%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Server</th>
                        <th class="w-[11%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Uptime</th>
                        <th class="w-[18%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">CPU Load</th>
                        <th class="w-[18%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Load RAM</th>
                        <th class="w-[18%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Traffic</th>
                        <th class="w-[19%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Disk Free</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="rows.length === 0">
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Belum ada data statistik server dari PRTG.
                            </td>
                        </tr>
                    </template>

                    <template x-for="row in rows" :key="row.id">
                        <tr class="border-b border-gray-100 align-top dark:border-gray-800">
                            <td class="truncate px-2 py-2 text-xs font-medium text-gray-800 dark:text-white/90" :title="row.server" x-text="row.server"></td>

                            <td class="px-2 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <template x-if="!hasSubmitted && row.metrics.ping.available">
                                    <div class="h-8 leading-8 text-gray-400">Pilih periode</div>
                                </template>
                                <template x-if="row.metrics.ping.loading">
                                    <div class="flex h-8 items-center justify-center" x-html="spinnerHtml()"></div>
                                </template>
                                <template x-if="!row.metrics.ping.loading && !row.metrics.ping.available">
                                    <div class="h-8 leading-8">-</div>
                                </template>
                                <template x-if="hasSubmitted && !row.metrics.ping.loading && row.metrics.ping.available">
                                    <div>
                                        <div class="mb-1" x-text="formatPercent(row.metrics.ping.uptime_percent)"></div>
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div class="h-full" :class="uptimeBarClass(row.metrics.ping.uptime_percent)" :style="`width: ${barWidth(row.metrics.ping.uptime_percent)}%`"></div>
                                        </div>
                                    </div>
                                </template>
                            </td>

                            <td class="px-2 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <template x-if="!hasSubmitted && row.metrics.cpu.available">
                                    <div class="h-14 leading-[56px] text-gray-400">Pilih periode</div>
                                </template>
                                <template x-if="row.metrics.cpu.loading">
                                    <div class="flex h-14 items-center justify-center" x-html="spinnerHtml()"></div>
                                </template>
                                <template x-if="!row.metrics.cpu.loading && !row.metrics.cpu.available">
                                    <div class="h-14 leading-[56px]">-</div>
                                </template>
                                <template x-if="hasSubmitted && !row.metrics.cpu.loading && row.metrics.cpu.available">
                                    <div class="space-y-1">
                                        <template x-for="metricKey in ['min', 'max', 'avg']" :key="`cpu-${row.id}-${metricKey}`">
                                            <div>
                                                <div class="mb-0.5 flex justify-between text-[11px]">
                                                    <span class="uppercase" x-text="metricKey"></span>
                                                    <span x-text="formatPercent(row.metrics.cpu.stats[metricKey])"></span>
                                                </div>
                                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div class="h-full" :class="metricBarClass('cpu', row.metrics.cpu.bars[metricKey])" :style="`width: ${barWidth(row.metrics.cpu.bars[metricKey])}%`"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </td>

                            <td class="px-2 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <template x-if="!hasSubmitted && row.metrics.ram.available">
                                    <div class="h-14 leading-[56px] text-gray-400">Pilih periode</div>
                                </template>
                                <template x-if="row.metrics.ram.loading">
                                    <div class="flex h-14 items-center justify-center" x-html="spinnerHtml()"></div>
                                </template>
                                <template x-if="!row.metrics.ram.loading && !row.metrics.ram.available">
                                    <div class="h-14 leading-[56px]">-</div>
                                </template>
                                <template x-if="hasSubmitted && !row.metrics.ram.loading && row.metrics.ram.available">
                                    <div class="space-y-1">
                                        <template x-for="metricKey in ['min', 'max', 'avg']" :key="`ram-${row.id}-${metricKey}`">
                                            <div>
                                                <div class="mb-0.5 flex justify-between text-[11px]">
                                                    <span class="uppercase" x-text="metricKey"></span>
                                                    <span x-text="formatPercent(row.metrics.ram.stats[metricKey])"></span>
                                                </div>
                                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div class="h-full" :class="metricBarClass('ram_load', row.metrics.ram.bars[metricKey])" :style="`width: ${barWidth(row.metrics.ram.bars[metricKey])}%`"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </td>

                            <td class="px-2 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <template x-if="!hasSubmitted && row.metrics.traffic.available">
                                    <div class="h-14 leading-[56px] text-gray-400">Pilih periode</div>
                                </template>
                                <template x-if="row.metrics.traffic.loading">
                                    <div class="flex h-14 items-center justify-center" x-html="spinnerHtml()"></div>
                                </template>
                                <template x-if="!row.metrics.traffic.loading && !row.metrics.traffic.available">
                                    <div class="h-14 leading-[56px]">-</div>
                                </template>
                                <template x-if="hasSubmitted && !row.metrics.traffic.loading && row.metrics.traffic.available">
                                    <div class="space-y-1">
                                        <template x-for="metricKey in ['min', 'max', 'avg']" :key="`traffic-${row.id}-${metricKey}`">
                                            <div>
                                                <div class="mb-0.5 flex justify-between text-[11px]">
                                                    <span class="uppercase" x-text="metricKey"></span>
                                                    <span x-text="formatTraffic(row.metrics.traffic.stats[metricKey])"></span>
                                                </div>
                                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div class="h-full" :class="metricBarClass('traffic', row.metrics.traffic.bars[metricKey])" :style="`width: ${barWidth(row.metrics.traffic.bars[metricKey])}%`"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </td>

                            <td class="px-2 py-2 text-xs text-gray-700 dark:text-gray-300">
                                <template x-if="!hasSubmitted && row.metrics.disk.available">
                                    <div class="h-14 leading-[56px] text-gray-400">Pilih periode</div>
                                </template>
                                <template x-if="row.metrics.disk.loading">
                                    <div class="flex h-14 items-center justify-center" x-html="spinnerHtml()"></div>
                                </template>
                                <template x-if="!row.metrics.disk.loading && !row.metrics.disk.available">
                                    <div class="h-14 leading-[56px]">-</div>
                                </template>
                                <template x-if="hasSubmitted && !row.metrics.disk.loading && row.metrics.disk.available">
                                    <div class="space-y-1">
                                        <template x-for="metricKey in ['min', 'max', 'avg']" :key="`disk-${row.id}-${metricKey}`">
                                            <div>
                                                <div class="mb-0.5 flex justify-between text-[11px]">
                                                    <span class="font-medium uppercase tracking-wide text-gray-600 dark:text-gray-400" x-text="diskStatRowLabel(metricKey)"></span>
                                                    <span class="tabular-nums" x-text="diskFreeRowDisplay(metricKey, row)"></span>
                                                </div>
                                                <template x-if="metricKey !== 'avg'">
                                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                        <div class="h-full" :class="metricBarClass('disk', row.metrics.disk.bars[metricKey])" :style="`width: ${barWidth(row.metrics.disk.bars[metricKey])}%`"></div>
                                                    </div>
                                                </template>
                                                <template x-if="metricKey === 'avg'">
                                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                        <div class="h-full" :class="diskDeltaBarClass(row.metrics.disk.stats.avg)" :style="`width: ${diskDeltaBarWidth(row.metrics.disk.stats.avg)}%`"></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
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
