@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Aset TI - Dashboard Perangkat DC DRC" />
    @php
        $hostPalette = ['#06b6d4', '#8b5cf6'];
        $brandPalette = ['#10b981', '#06b6d4', '#8b5cf6', '#f59e0b', '#ef4444', '#3b82f6', '#22c55e', '#a855f7', '#14b8a6', '#f97316'];

        $buildDonut = function (array $labels, array $totals, array $palette) {
            $sum = array_sum($totals);
            if ($sum <= 0) {
                return [
                    'gradient' => '#1f2937 0% 100%',
                    'items' => [],
                    'sum' => 0,
                ];
            }

            $current = 0.0;
            $segments = [];
            $items = [];
            foreach ($labels as $idx => $label) {
                $value = (float) ($totals[$idx] ?? 0);
                if ($value <= 0) {
                    continue;
                }
                $percent = ($value / $sum) * 100;
                $start = $current;
                $end = $current + $percent;
                $color = $palette[$idx % count($palette)];
                $segments[] = sprintf('%s %.3f%% %.3f%%', $color, $start, $end);
                $items[] = [
                    'label' => $label,
                    'total' => (int) $value,
                    'percent' => round($percent, 1),
                    'color' => $color,
                ];
                $current = $end;
            }

            return [
                'gradient' => implode(', ', $segments),
                'items' => $items,
                'sum' => (int) $sum,
            ];
        };

        $hostTypeDonut = $buildDonut($hostTypeLabels, $hostTypeTotals, $hostPalette);
        $brandDonut = $buildDonut($brandLabels->all(), $brandTotals->all(), $brandPalette);
        $vmPerHostDonut = $buildDonut($vmPerHostLabels->all(), $vmPerHostTotals->all(), ['#22c55e', '#06b6d4', '#8b5cf6', '#f59e0b', '#ef4444', '#3b82f6']);
    @endphp

    <div x-data="{
        showChartModal: false,
        activeChart: '',
        charts: @js([
            'host_type' => ['title' => 'Jumlah Server Baremetal vs VM Host', 'unit' => 'Unit', 'sum' => $hostTypeDonut['sum'], 'items' => $hostTypeDonut['items']],
            'brand' => ['title' => 'Merk Server (berdasarkan NIC/Model)', 'unit' => 'Unit', 'sum' => $brandDonut['sum'], 'items' => $brandDonut['items']],
            'vm_per_host' => ['title' => 'Jumlah VM pada Tiap VM Host', 'unit' => 'VM', 'sum' => $vmPerHostDonut['sum'], 'items' => $vmPerHostDonut['items']],
        ]),
        openChart(key) {
            this.activeChart = key;
            this.showChartModal = true;
        },
        currentChart() {
            return this.charts[this.activeChart] ?? null;
        },
        createDonut(items) {
            return {
                items: items || [],
                hover: null,
                tipX: 0,
                tipY: 0,
                ranges: [],
                gradient: '#1f2937 0% 100%',
                init() {
                    this.setItems(this.items);
                },
                setItems(newItems) {
                    this.items = newItems || [];
                    let current = 0;
                    this.ranges = this.items.map((item) => {
                        const start = current;
                        const end = current + Number(item.percent || 0);
                        current = end;
                        return { ...item, start, end };
                    });

                    if (this.ranges.length) {
                        this.gradient = this.ranges
                            .map((item) => `${item.color} ${item.start}% ${item.end}%`)
                            .join(', ');
                    } else {
                        this.gradient = '#1f2937 0% 100%';
                    }
                    this.hover = null;
                },
                onMove(event) {
                    if (!this.ranges.length) {
                        this.hover = null;
                        return;
                    }
                    const rect = event.currentTarget.getBoundingClientRect();
                    const cx = rect.left + rect.width / 2;
                    const cy = rect.top + rect.height / 2;
                    const dx = event.clientX - cx;
                    const dy = event.clientY - cy;
                    const r = Math.sqrt((dx * dx) + (dy * dy));
                    const outer = rect.width / 2;
                    const inner = outer * 0.56;
                    if (r < inner || r > outer) {
                        this.hover = null;
                        return;
                    }
                    const angle = (Math.atan2(dy, dx) * (180 / Math.PI) + 90 + 360) % 360;
                    const pct = angle / 3.6;
                    this.hover = this.ranges.find((item) => pct >= item.start && pct < item.end) ?? this.ranges[this.ranges.length - 1];
                    this.tipX = event.offsetX + 12;
                    this.tipY = event.offsetY + 12;
                },
                clear() {
                    this.hover = null;
                },
            };
        },
    }">
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="cursor-pointer rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 dark:border-gray-800 dark:bg-white/[0.03]" @click="openChart('host_type')">
            <h3 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Jumlah Server Baremetal vs VM Host</h3>
            <div class="flex items-center gap-6">
                <div class="relative h-44 w-44 shrink-0 rounded-full"
                    x-data="createDonut(@js($hostTypeDonut['items']))"
                    @mousemove="onMove($event)"
                    @mouseleave="clear()"
                    style="background: conic-gradient({{ $hostTypeDonut['gradient'] }});">
                    <div class="absolute inset-[22%] rounded-full bg-white dark:bg-gray-900"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200">
                        {{ $hostTypeDonut['sum'] }} Unit
                    </div>
                    <div x-show="hover" x-cloak class="pointer-events-none absolute z-20 rounded-md bg-gray-900/90 px-2 py-1 text-[11px] text-white"
                        :style="`left:${tipX}px; top:${tipY}px;`">
                        <span x-text="`${hover?.label}: ${hover?.total}`"></span>
                    </div>
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                    @forelse ($hostTypeDonut['items'] as $item)
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                            </div>
                            <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $item['total'] }} ({{ $item['percent'] }}%)</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data perangkat.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="cursor-pointer rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 dark:border-gray-800 dark:bg-white/[0.03]" @click="openChart('brand')">
            <h3 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Merk Server (berdasarkan NIC/Model)</h3>
            <div class="flex items-center gap-6">
                <div class="relative h-44 w-44 shrink-0 rounded-full"
                    x-data="createDonut(@js($brandDonut['items']))"
                    @mousemove="onMove($event)"
                    @mouseleave="clear()"
                    style="background: conic-gradient({{ $brandDonut['gradient'] }});">
                    <div class="absolute inset-[22%] rounded-full bg-white dark:bg-gray-900"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200">
                        {{ $brandDonut['sum'] }} Unit
                    </div>
                    <div x-show="hover" x-cloak class="pointer-events-none absolute z-20 rounded-md bg-gray-900/90 px-2 py-1 text-[11px] text-white"
                        :style="`left:${tipX}px; top:${tipY}px;`">
                        <span x-text="`${hover?.label}: ${hover?.total}`"></span>
                    </div>
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                    @forelse ($brandDonut['items'] as $item)
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                            </div>
                            <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $item['total'] }} ({{ $item['percent'] }}%)</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data merk/model server.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 cursor-pointer rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 dark:border-gray-800 dark:bg-white/[0.03]" @click="openChart('vm_per_host')">
        <h3 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Jumlah VM pada Tiap VM Host</h3>
        <div class="flex items-center gap-6">
            <div class="relative h-44 w-44 shrink-0 rounded-full"
                x-data="createDonut(@js($vmPerHostDonut['items']))"
                @mousemove="onMove($event)"
                @mouseleave="clear()"
                style="background: conic-gradient({{ $vmPerHostDonut['gradient'] }});">
                <div class="absolute inset-[22%] rounded-full bg-white dark:bg-gray-900"></div>
                <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200">
                    {{ $vmPerHostDonut['sum'] }} VM
                </div>
                <div x-show="hover" x-cloak class="pointer-events-none absolute z-20 rounded-md bg-gray-900/90 px-2 py-1 text-[11px] text-white"
                    :style="`left:${tipX}px; top:${tipY}px;`">
                    <span x-text="`${hover?.label}: ${hover?.total}`"></span>
                </div>
            </div>
            <div class="min-w-0 flex-1 space-y-2">
                @forelse ($vmPerHostDonut['items'] as $item)
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                        </div>
                        <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $item['total'] }} VM ({{ $item['percent'] }}%)</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data VM host / VM.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div x-show="showChartModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/60 p-4"
        @click.self="showChartModal = false">
        <div class="w-full max-w-4xl rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="currentChart()?.title ?? 'Detail Chart'"></h4>
                <button type="button" @click="showChartModal = false"
                    class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
                    Tutup
                </button>
            </div>
            <template x-if="currentChart()">
                <div class="flex items-center gap-8">
                    <div class="relative h-72 w-72 shrink-0 rounded-full"
                        x-data="createDonut(currentChart()?.items || [])"
                        x-effect="setItems($root.currentChart()?.items || [])"
                        @mousemove="onMove($event)"
                        @mouseleave="clear()"
                        :style="`background: conic-gradient(${gradient});`">
                        <div class="absolute inset-[22%] rounded-full bg-white dark:bg-gray-900"></div>
                        <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-gray-700 dark:text-gray-200"
                            x-text="`${currentChart()?.sum ?? 0} ${currentChart()?.unit ?? 'Unit'}`"></div>
                        <div x-show="hover" x-cloak class="pointer-events-none absolute z-20 rounded-md bg-gray-900/90 px-2 py-1 text-[11px] text-white"
                            :style="`left:${tipX}px; top:${tipY}px;`">
                            <span x-text="`${hover?.label}: ${hover?.total}`"></span>
                        </div>
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <template x-for="item in (currentChart()?.items || [])" :key="item.label">
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" :style="`background-color:${item.color}`"></span>
                                    <span class="truncate text-gray-700 dark:text-gray-300" x-text="item.label"></span>
                                </div>
                                <span class="shrink-0 text-gray-500 dark:text-gray-400" x-text="`${item.total} (${item.percent}%)`"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
    </div>
@endsection
