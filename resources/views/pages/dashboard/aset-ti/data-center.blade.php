@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Aset TI - Data Center" />

    <div x-data="{
        rows: [],
        loading: true,
        errorMessage: null,
        updatedAt: '',
        dataUrl: @js($metricsUrl),
        async init() {
            await this.loadData();
        },
        async loadData() {
            this.loading = true;
            this.errorMessage = null;
            try {
                const response = await fetch(this.dataUrl, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                this.rows = Array.isArray(payload.rows) ? payload.rows : [];
                this.updatedAt = payload.updatedAt || '';
                this.errorMessage = payload.errorMessage || null;
            } catch (error) {
                this.rows = [];
                this.errorMessage = 'Gagal mengambil data dashboard Data Center.';
            } finally {
                this.loading = false;
            }
        },
        donutStyle(percent, color) {
            if (percent === null || percent === undefined || Number.isNaN(Number(percent))) {
                return 'background: conic-gradient(#9ca3af 0% 100%);';
            }
            const p = Math.max(0, Math.min(100, Number(percent)));
            return `background: conic-gradient(${color} 0% ${p}%, #e5e7eb ${p}% 100%);`;
        },
        percentText(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) return '-';
            return `${Number(value).toFixed(1)}%`;
        },
        trafficText(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) return '-';
            const numeric = Number(value);
            if (Math.abs(numeric) < 1) {
                return `${(numeric * 1000).toFixed(2)} Kbit/s`;
            }
            return `${numeric.toFixed(3)} Mbit/s`;
        },
        trafficScale(points) {
            if (!Array.isArray(points) || points.length === 0) {
                return { unit: 'Mbit/s', multiplier: 1 };
            }

            const maxAbs = Math.max(...points.map((p) => Math.abs(Number(p.value || 0))));
            if (maxAbs < 1) {
                return { unit: 'Kbit/s', multiplier: 1000 };
            }

            return { unit: 'Mbit/s', multiplier: 1 };
        },
        trafficAxisLabels(points, ticks = 5) {
            const scale = this.trafficScale(points);
            if (!Array.isArray(points) || points.length === 0) {
                return Array.from({ length: ticks }, (_, index) => ({
                    key: index,
                    text: `0 ${scale.unit}`,
                }));
            }

            const values = points.map((p) => Number(p.value || 0) * scale.multiplier);
            const min = Math.min(...values);
            const max = Math.max(...values);
            const labels = [];

            for (let i = 0; i < ticks; i++) {
                const ratio = ticks === 1 ? 0 : i / (ticks - 1);
                const value = max - ((max - min) * ratio);
                const precision = value < 10 ? 2 : 1;
                labels.push({
                    key: i,
                    text: `${value.toFixed(precision)} ${scale.unit}`,
                });
            }

            return labels;
        },
        chartPath(points, width = 520, height = 120, pad = 10) {
            if (!Array.isArray(points) || points.length === 0) return '';
            const scale = this.trafficScale(points);
            const values = points.map((p) => Number(p.value || 0) * scale.multiplier);
            const min = Math.min(...values);
            const max = Math.max(...values);
            const step = points.length > 1 ? (width - (pad * 2)) / (points.length - 1) : 0;

            if (max === min) {
                const y = height / 2;
                return points.map((point, index) => {
                    const x = pad + (step * index);
                    return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
                }).join(' ');
            }

            const range = max - min;

            return points.map((point, index) => {
                const x = pad + (step * index);
                const scaledValue = Number(point.value || 0) * scale.multiplier;
                const y = height - pad - (((scaledValue - min) / range) * (height - (pad * 2)));
                return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
            }).join(' ');
        },
        chartHourTicks(points, maxLabels = 8) {
            if (!Array.isArray(points) || points.length === 0) return [];

            const count = points.length;
            const labelsTarget = Math.max(2, Math.min(maxLabels, count));
            const step = count <= labelsTarget ? 1 : Math.ceil((count - 1) / (labelsTarget - 1));
            const ticks = [];

            for (let i = 0; i < count; i += step) {
                ticks.push({
                    key: i,
                    label: points[i]?.label ?? '-',
                    leftPercent: count === 1 ? 0 : (i / (count - 1)) * 100,
                });
            }

            const lastIndex = count - 1;
            if (!ticks.some((tick) => tick.key === lastIndex)) {
                ticks.push({
                    key: lastIndex,
                    label: points[lastIndex]?.label ?? '-',
                    leftPercent: 100,
                });
            }

            return ticks;
        },
    }" class="flex min-h-0 h-full flex-col space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Dashboard Per Server</h3>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="updatedAt ? `Update: ${updatedAt}` : ''"></span>
                <button type="button" @click="loadData()"
                    class="inline-flex h-9 items-center rounded-lg border border-brand-500 px-3 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                    Refresh
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-auto pr-1">
            <div x-show="loading" x-cloak class="rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                Memuat dashboard Data Center...
            </div>
            <div x-show="errorMessage" x-cloak class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                <span x-text="errorMessage"></span>
            </div>

            <template x-if="!loading && rows.length === 0">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                    Belum ada perangkat server dengan OBJID metric.
                </div>
            </template>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <template x-for="row in rows" :key="row.server">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                        <h4 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90" x-text="row.server"></h4>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                <div class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">CPU Load</div>
                                <div class="mx-auto relative h-24 w-24 rounded-full"
                                    :style="donutStyle(row.cpu.percent, '#ef4444')">
                                    <div class="absolute inset-[24%] rounded-full bg-white dark:bg-gray-900"></div>
                                    <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200"
                                        x-text="percentText(row.cpu.percent)"></div>
                                </div>
                                <div class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400" x-text="row.cpu.text"></div>
                            </div>

                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                <div class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Free RAM</div>
                                <div class="mx-auto relative h-24 w-24 rounded-full"
                                    :style="donutStyle(row.ram.percent, '#22c55e')">
                                    <div class="absolute inset-[24%] rounded-full bg-white dark:bg-gray-900"></div>
                                    <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200"
                                        x-text="percentText(row.ram.percent)"></div>
                                </div>
                                <div class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400" x-text="row.ram.text"></div>
                            </div>

                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                                <div class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Disk Free</div>
                                <div class="mx-auto relative h-24 w-24 rounded-full"
                                    :style="donutStyle(row.disk.percent, '#3b82f6')">
                                    <div class="absolute inset-[24%] rounded-full bg-white dark:bg-gray-900"></div>
                                    <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200"
                                        x-text="percentText(row.disk.percent)"></div>
                                </div>
                                <div class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400" x-text="row.disk.text"></div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <div class="mb-2 flex items-center justify-between">
                                <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Traffic 1 Hari</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Terakhir: <span x-text="trafficText(row.traffic.latest_mbps)"></span>
                                </div>
                            </div>
                            <div class="mb-2 text-[10px] text-gray-400" x-text="`Satuan grafik: ${trafficScale(row.traffic.points).unit}`"></div>

                            <template x-if="row.traffic.available && row.traffic.points.length > 1">
                                <div>
                                    <div class="flex items-stretch gap-2">
                                        <div class="w-16 shrink-0 text-[10px] text-gray-400">
                                            <template x-for="label in trafficAxisLabels(row.traffic.points)" :key="label.key">
                                                <div class="flex h-6 items-center justify-end text-right leading-none" x-text="label.text"></div>
                                            </template>
                                        </div>
                                        <div class="flex-1">
                                            <svg viewBox="0 0 520 120" class="h-32 w-full">
                                                <path :d="chartPath(row.traffic.points)" fill="none" stroke="#8b5cf6" stroke-width="2.5" stroke-linecap="round"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="relative mt-2 h-6 border-t border-gray-300 dark:border-gray-600">
                                        <template x-for="tick in chartHourTicks(row.traffic.points)" :key="tick.key">
                                            <div class="absolute top-0 -translate-x-1/2 text-[10px] text-gray-400"
                                                :style="`left:${tick.leftPercent}%`">
                                                <div class="mx-auto h-2 w-px bg-gray-300 dark:bg-gray-600"></div>
                                                <div class="mt-1 whitespace-nowrap" x-text="tick.label"></div>
                                            </div>
                                        </template>
                                        <div class="absolute right-0 top-0 h-2 w-px bg-gray-300 dark:bg-gray-600"></div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!row.traffic.available || row.traffic.points.length <= 1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Data traffic belum tersedia.</p>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endsection
