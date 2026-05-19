@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Aset TI - Data Center" />

    <div x-data="{
        rows: [],
        loading: true,
        errorMessage: null,
        updatedAt: '',
        dataUrl: @js($metricsUrl),
        activePanel: 0,
        dcTouchStartX: null,
        async init() {
            try {
                const url = new URL(window.location.href);
                if (url.searchParams.get('tab') === 'dc-drc') {
                    this.activePanel = 1;
                }
            } catch (e) {
                /* ignore */
            }
            await this.loadData();
        },
        dcSwipeStart(e) {
            const t = e.touches && e.touches[0];
            if (t) {
                this.dcTouchStartX = t.clientX;
            }
        },
        dcSwipeEnd(e) {
            if (this.dcTouchStartX === null) {
                return;
            }
            const t = e.changedTouches && e.changedTouches[0];
            if (!t) {
                this.dcTouchStartX = null;
                return;
            }
            const dx = t.clientX - this.dcTouchStartX;
            this.dcTouchStartX = null;
            if (dx < -60 && this.activePanel < 1) {
                this.setDcPanel(1);
            } else if (dx > 60 && this.activePanel > 0) {
                this.setDcPanel(0);
            }
        },
        setDcPanel(index) {
            this.activePanel = index;
            try {
                const url = new URL(window.location.href);
                if (index === 1) {
                    url.searchParams.set('tab', 'dc-drc');
                } else {
                    url.searchParams.delete('tab');
                }
                window.history.replaceState({}, '', url);
            } catch (e) {
                /* ignore */
            }
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
            const oppositeColor = this.donutOppositeColor(color);
            return `background: conic-gradient(${color} 0% ${p}%, ${oppositeColor} ${p}% 100%);`;
        },
        donutOppositeColor(color) {
            return color === '#ef4444' ? '#22c55e' : '#ef4444';
        },
        percentText(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) return '-';
            return `${Number(value).toFixed(1)}%`;
        },
        // Nilai API/PRTG untuk RAM di kartu ini = % free/tersedia; RAM load = 100 - free (hanya tampilan, endpoint tetap sama).
        ramLoadPercent(freePercent) {
            if (freePercent === null || freePercent === undefined || Number.isNaN(Number(freePercent))) return null;
            return Math.max(0, Math.min(100, 100 - Number(freePercent)));
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
        /** Palet aksen per kartu (mirip mockup: ungu, biru, magenta, hijau, …) */
        serverAccentThemes: [
            { rgb: '139 92 246', iconBg: 'bg-violet-500', ringIcon: 'ring-violet-300/40' },
            { rgb: '59 130 246', iconBg: 'bg-blue-500', ringIcon: 'ring-blue-300/40' },
            { rgb: '217 70 239', iconBg: 'bg-fuchsia-500', ringIcon: 'ring-fuchsia-300/40' },
            { rgb: '52 211 153', iconBg: 'bg-emerald-400', ringIcon: 'ring-emerald-300/40' },
            { rgb: '14 165 233', iconBg: 'bg-sky-500', ringIcon: 'ring-sky-300/40' },
            { rgb: '251 146 60', iconBg: 'bg-orange-400', ringIcon: 'ring-orange-300/40' },
        ],
        serverAccent(serverName) {
            const list = this.serverAccentThemes || [];
            const len = Math.max(list.length, 1);
            let h = 0;
            const s = String(serverName ?? '');
            for (let i = 0; i < s.length; i++) {
                h = ((h << 5) - h) + s.charCodeAt(i);
                h |= 0;
            }
            return list[Math.abs(h) % len];
        },
        serverCardBgLight(serverName) {
            const { rgb } = this.serverAccent(serverName);
            return {
                backgroundImage: `linear-gradient(135deg, rgb(${rgb} / 0.085) 0%, rgb(${rgb} / 0.03) 28%, rgb(255 255 255) 52%)`,
            };
        },
        serverCardBgDark(serverName) {
            const { rgb } = this.serverAccent(serverName);
            return {
                backgroundImage: `linear-gradient(135deg, rgb(${rgb} / 0.24) 0%, rgb(${rgb} / 0.08) 38%, rgb(15 23 42 / 0.94) 62%, rgb(15 23 42 / 0.99) 100%)`,
            };
        },
        chartStrokeRgb(serverName) {
            const { rgb } = this.serverAccent(serverName);
            return `rgb(${rgb})`;
        },
        chartFillPath(points, width = 520, height = 120, pad = 10) {
            if (!Array.isArray(points) || points.length === 0) return '';
            const scale = this.trafficScale(points);
            const values = points.map((p) => Number(p.value || 0) * scale.multiplier);
            const min = Math.min(...values);
            const max = Math.max(...values);
            const step = points.length > 1 ? (width - (pad * 2)) / (points.length - 1) : 0;
            const bottom = height - pad;
            let d = `M ${pad} ${bottom}`;
            points.forEach((point, index) => {
                const x = pad + (step * index);
                let y;
                if (max === min) {
                    y = height / 2;
                } else {
                    const scaledValue = Number(point.value || 0) * scale.multiplier;
                    y = height - pad - (((scaledValue - min) / (max - min)) * (height - (pad * 2)));
                }
                d += ` L ${x.toFixed(2)} ${y.toFixed(2)}`;
            });
            const lastX = pad + (step * (points.length - 1));
            d += ` L ${lastX.toFixed(2)} ${bottom} Z`;
            return d;
        },
    }" class="flex h-full min-h-0 flex-col gap-3 overflow-hidden">
        <div class="flex flex-wrap items-center gap-2" role="tablist">
            <button type="button" role="tab" :aria-selected="activePanel === 0" @click="setDcPanel(0)"
                class="rounded-full px-4 py-2 text-sm font-semibold transition-all duration-200"
                :class="activePanel === 0
                    ? 'border-2 border-brand-500 bg-white text-brand-600 shadow-sm shadow-brand-500/10 dark:bg-gray-900 dark:text-brand-400 dark:shadow-brand-500/5'
                    : 'border border-transparent bg-gray-100 text-gray-500 hover:bg-gray-200/70 hover:text-gray-800 dark:bg-gray-800/80 dark:text-gray-400 dark:hover:bg-gray-800'">
                Monitoring per server (PRTG)
            </button>
            <button type="button" role="tab" :aria-selected="activePanel === 1" @click="setDcPanel(1)"
                class="rounded-full px-4 py-2 text-sm font-semibold transition-all duration-200"
                :class="activePanel === 1
                    ? 'border-2 border-brand-500 bg-white text-brand-600 shadow-sm shadow-brand-500/10 dark:bg-gray-900 dark:text-brand-400 dark:shadow-brand-500/5'
                    : 'border border-transparent bg-gray-100 text-gray-500 hover:bg-gray-200/70 hover:text-gray-800 dark:bg-gray-800/80 dark:text-gray-400 dark:hover:bg-gray-800'">
                Inventaris DC-DRC
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden">
            <div class="flex h-full min-h-0 min-w-0 flex-1 touch-pan-y transition-transform duration-300 ease-out w-[200%]"
                 :style="'transform: translateX(-' + (activePanel * 50) + '%)'"
                 @touchstart.passive="dcSwipeStart($event)"
                 @touchend="dcSwipeEnd($event)">
                <div class="h-full w-1/2 shrink-0 flex min-h-0 flex-col overflow-hidden pr-2">
                    <div class="flex flex-col gap-4 pb-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-xl font-bold tracking-tight text-slate-800 dark:text-white/95">Dashboard Per Server</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan performa server secara real-time</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-3 py-2 text-xs font-medium text-gray-600 ring-1 ring-gray-200/80 dark:bg-gray-800/60 dark:text-gray-300 dark:ring-gray-700">
                                <svg class="h-4 w-4 shrink-0 text-brand-500 dark:text-brand-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                                <span x-text="updatedAt ? `Update: ${updatedAt}` : 'Menunggu data…'"></span>
                            </span>
                            <button type="button" @click="loadData()"
                                class="inline-flex h-10 items-center gap-2 rounded-xl border-2 border-brand-500 bg-white px-4 text-sm font-semibold text-brand-600 shadow-sm transition hover:bg-brand-50 active:scale-[0.98] dark:border-brand-400 dark:bg-gray-900 dark:text-brand-300 dark:hover:bg-brand-500/10">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                Refresh
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto pr-1">
            <div x-show="loading" x-cloak class="content-card p-5 text-sm text-gray-500 dark:text-gray-300">
                Memuat dashboard Data Center...
            </div>
            <div x-show="errorMessage" x-cloak class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                <span x-text="errorMessage"></span>
            </div>

            <template x-if="!loading && rows.length === 0">
                <div class="content-card p-5 text-sm text-gray-500 dark:text-gray-300">
                    Belum ada perangkat server dengan OBJID metric.
                </div>
            </template>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <template x-for="(row, index) in rows" :key="`${row.server}-${index}`">
                    <div
                        class="relative overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-lg shadow-slate-900/[0.06] ring-1 ring-slate-900/[0.035] transition-shadow duration-300 hover:shadow-xl hover:shadow-slate-900/[0.08] dark:border-slate-600/50 dark:bg-slate-900 dark:shadow-black/50 dark:ring-white/[0.06]">
                        <div class="pointer-events-none absolute inset-0 rounded-2xl dark:hidden" :style="serverCardBgLight(row.server)"></div>
                        <div class="pointer-events-none absolute inset-0 hidden rounded-2xl dark:block" :style="serverCardBgDark(row.server)"></div>
                        <div class="relative z-10 p-5">
                        <div class="mb-5 flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-md ring-2 ring-black/10 dark:ring-white/15"
                                :class="[serverAccent(row.server).iconBg, serverAccent(row.server).ringIcon]">
                                <svg class="h-6 w-6 opacity-95" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-.879-5.894 6 6 0 1 1 11.758-.891M5.25 14.25h13.5V18a2.25 2.25 0 0 1-2.25 2.25H7.5A2.25 2.25 0 0 1 5.25 18v-3.75z" />
                                </svg>
                            </div>
                            <h4 class="text-lg font-bold tracking-tight text-slate-800 dark:text-white" x-text="row.server"></h4>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-gray-200/80 bg-white/55 p-3 backdrop-blur-sm dark:border-slate-600/35 dark:bg-slate-950/75 dark:backdrop-blur-md">
                                <div class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-300">CPU Load</div>
                                <div class="mx-auto relative h-24 w-24 rounded-full"
                                    :style="donutStyle(row.cpu.percent, '#ef4444')">
                                    <div class="absolute inset-[24%] rounded-full bg-white/95 dark:bg-slate-900"></div>
                                    <div class="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-800 dark:text-gray-50"
                                        x-text="percentText(row.cpu.percent)"></div>
                                </div>
                                <div class="mt-1 truncate text-center text-[11px] text-gray-500 dark:text-slate-400" x-text="row.cpu.text"></div>
                            </div>

                            <div class="rounded-xl border border-gray-200/80 bg-white/55 p-3 backdrop-blur-sm dark:border-slate-600/35 dark:bg-slate-950/75 dark:backdrop-blur-md">
                                <div class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-300">RAM Load</div>
                                <div class="mx-auto relative h-24 w-24 rounded-full"
                                    :style="donutStyle(ramLoadPercent(row.ram.percent), '#ef4444')">
                                    <div class="absolute inset-[24%] rounded-full bg-white/95 dark:bg-slate-900"></div>
                                    <div class="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-800 dark:text-gray-50"
                                        x-text="percentText(ramLoadPercent(row.ram.percent))"></div>
                                </div>
                                <div class="mt-1 truncate text-center text-[11px] text-gray-500 dark:text-slate-400" x-text="row.ram.text"></div>
                            </div>

                            <div class="rounded-xl border border-gray-200/80 bg-white/55 p-3 backdrop-blur-sm dark:border-slate-600/35 dark:bg-slate-950/75 dark:backdrop-blur-md">
                                <div class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-300">Disk Free</div>
                                <div class="mx-auto relative h-24 w-24 rounded-full"
                                    :style="donutStyle(row.disk.percent, '#22c55e')">
                                    <div class="absolute inset-[24%] rounded-full bg-white/95 dark:bg-slate-900"></div>
                                    <div class="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-800 dark:text-gray-50"
                                        x-text="percentText(row.disk.percent)"></div>
                                </div>
                                <div class="mt-1 truncate text-center text-[11px] text-gray-500 dark:text-slate-400" x-text="row.disk.text"></div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-gray-200/80 bg-white/50 p-4 backdrop-blur-sm dark:border-slate-600/35 dark:bg-slate-950/75 dark:backdrop-blur-md">
                            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-slate-200">
                                    <svg class="h-4 w-4 text-gray-400 dark:text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z" />
                                    </svg>
                                    Traffic 1 Hari
                                </div>
                                <div class="text-xs font-medium text-gray-500 dark:text-slate-400">
                                    Terakhir: <span class="text-gray-700 dark:text-white" x-text="trafficText(row.traffic.latest_mbps)"></span>
                                </div>
                            </div>
                            <div class="mb-3 flex flex-wrap justify-between gap-1 text-[10px] text-gray-400 dark:text-slate-500">
                                <span x-text="`Satuan grafik: ${trafficScale(row.traffic.points).unit}`"></span>
                            </div>

                            <template x-if="row.traffic.available && row.traffic.points.length > 1">
                                <div>
                                    <div class="flex items-stretch gap-2">
                                        <div class="w-16 shrink-0 text-[10px] text-gray-400 dark:text-slate-400">
                                            <template x-for="label in trafficAxisLabels(row.traffic.points)" :key="label.key">
                                                <div class="flex h-6 items-center justify-end text-right leading-none" x-text="label.text"></div>
                                            </template>
                                        </div>
                                        <div class="flex-1">
                                            <svg viewBox="0 0 520 120" class="h-32 w-full overflow-visible">
                                                <defs>
                                                    <linearGradient :id="'traffic-fill-' + index" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="0%" :stop-color="chartStrokeRgb(row.server)" stop-opacity="0.35" />
                                                        <stop offset="100%" :stop-color="chartStrokeRgb(row.server)" stop-opacity="0.02" />
                                                    </linearGradient>
                                                </defs>
                                                <path :d="chartFillPath(row.traffic.points)" :fill="'url(#traffic-fill-' + index + ')'"></path>
                                                <path :d="chartPath(row.traffic.points)" fill="none" :stroke="chartStrokeRgb(row.server)" stroke-width="2.5" stroke-linecap="round"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="relative mt-2 h-6 border-t border-gray-200/90 dark:border-slate-600">
                                        <template x-for="tick in chartHourTicks(row.traffic.points)" :key="tick.key">
                                            <div class="absolute top-0 -translate-x-1/2 text-[10px] text-gray-400 dark:text-slate-400"
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
                    </div>
                </template>
            </div>
                    </div>
                </div>
                <div class="h-full w-1/2 shrink-0 flex min-h-0 flex-col overflow-hidden pl-2">
                    <div class="mb-1 shrink-0">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Dashboard inventaris DC-DRC</h3>
                    </div>
                    <div class="min-h-0 flex-1 overflow-hidden">
                        @include('pages.dashboard.aset-ti.partials.dc-drc-inventory-dashboard')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
