{{-- Layout ringkas agar muat satu layar; klik kartu untuk detail di modal --}}
{{-- Pewarnaan kartu diselaraskan dengan panel Monitoring per server (PRTG) di data-center.blade.php --}}
@php
    $dcInventoryCardAccents = [
        ['rgb' => '139 92 246'],
        ['rgb' => '59 130 246'],
        ['rgb' => '217 70 239'],
        ['rgb' => '52 211 153'],
    ];
@endphp
<div
    class="flex h-full min-h-0 flex-col gap-2 overflow-hidden"
    x-data="{
        showChartModal: false,
        activeChart: '',
        charts: @js([
            'host_type' => ['title' => 'Jumlah Server Baremetal vs VM Host', 'unit' => 'Unit', 'sum' => $hostTypeDonut['sum'], 'items' => $hostTypeDonut['items']],
            'brand' => ['title' => 'Merk Server (berdasarkan NIC/Model)', 'unit' => 'Unit', 'sum' => $brandDonut['sum'], 'items' => $brandDonut['items']],
            'vm_per_host' => ['title' => 'Jumlah VM pada Tiap VM Host', 'unit' => 'VM', 'sum' => $vmPerHostDonut['sum'], 'items' => $vmPerHostDonut['items']],
            'os' => ['title' => 'Sistem operasi perangkat', 'unit' => 'Unit', 'sum' => $osDonut['sum'], 'items' => $osDonut['items']],
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
    }"
>
    <div class="dc-drc-donut-grid grid min-h-0 flex-1 grid-cols-1 grid-rows-4 gap-4 overflow-hidden sm:grid-cols-2 sm:grid-rows-2 sm:gap-5">
        @php $__dcRgb = $dcInventoryCardAccents[0]['rgb']; @endphp
        <div
            class="dc-drc-donut-card relative flex h-full min-h-0 cursor-pointer flex-col overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-lg shadow-slate-900/[0.06] ring-1 ring-slate-900/[0.035] transition-[box-shadow] duration-300 hover:shadow-xl hover:shadow-slate-900/[0.08] dark:border-slate-600/50 dark:bg-slate-900 dark:shadow-black/50 dark:ring-white/[0.06] dark:hover:shadow-black/55"
            @click="openChart('host_type')"
        >
            <div class="pointer-events-none absolute inset-0 rounded-2xl dark:hidden" style="background-image: linear-gradient(135deg, rgb({{ $__dcRgb }} / 0.085) 0%, rgb({{ $__dcRgb }} / 0.03) 28%, rgb(255 255 255) 52%);"></div>
            <div class="pointer-events-none absolute inset-0 hidden rounded-2xl dark:block" style="background-image: linear-gradient(135deg, rgb({{ $__dcRgb }} / 0.24) 0%, rgb({{ $__dcRgb }} / 0.08) 38%, rgb(15 23 42 / 0.94) 62%, rgb(15 23 42 / 0.99) 100%);"></div>
            <div class="relative z-10 flex min-h-0 flex-1 flex-col overflow-hidden p-3">
            <h3 class="mb-2 shrink-0 text-xs font-semibold leading-tight text-gray-800 dark:text-white">Baremetal vs VM Host</h3>
            <div class="flex min-h-0 flex-1 items-stretch gap-2 overflow-hidden">
                <div class="flex h-full min-h-0 shrink-0 items-center justify-center">
                <div
                    class="relative aspect-square h-[70%] min-h-[5.5rem] min-w-[5.5rem] max-h-[10rem] max-w-[min(100%,10rem)] w-auto rounded-full"
                    x-data="createDonut(@js($hostTypeDonut['items']))"
                    @mousemove="onMove($event)"
                    @mouseleave="clear()"
                    style="background: conic-gradient({{ $hostTypeDonut['gradient'] }});"
                >
                    <div class="absolute inset-[24%] rounded-full bg-white/95 dark:bg-slate-900"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-gray-700 dark:text-gray-50">
                        {{ $hostTypeDonut['sum'] }}
                    </div>
                    <div x-show="hover" x-cloak class="pointer-events-none absolute z-20 rounded bg-gray-900/90 px-1.5 py-0.5 text-[10px] text-white"
                        :style="`left:${tipX}px; top:${tipY}px;`">
                        <span x-text="`${hover?.label}: ${hover?.total}`"></span>
                    </div>
                </div>
                </div>
                <div class="dc-drc-donut-legend grid min-h-0 min-w-0 flex-1 grid-flow-col grid-rows-[repeat(10,minmax(0,auto))] auto-cols-max gap-x-3 gap-y-0.5 overflow-x-auto overflow-y-hidden text-[10px] leading-tight">
                    @forelse ($hostTypeDonut['items'] as $item)
                        <div
                            class="flex max-w-[9.5rem] items-start justify-between gap-1 sm:max-w-[11rem]">
                            <div class="flex min-w-0 flex-1 items-center gap-1">
                                <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                <span class="line-clamp-2 break-words text-gray-700 dark:text-slate-300" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            </div>
                            <span class="shrink-0 tabular-nums text-gray-500 dark:text-slate-400">{{ $item['total'] }}</span>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-slate-400">Belum ada data.</p>
                    @endforelse
                </div>
            </div>
            </div>
        </div>

        @php $__dcRgb = $dcInventoryCardAccents[1]['rgb']; @endphp
        <div
            class="dc-drc-donut-card relative flex h-full min-h-0 cursor-pointer flex-col overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-lg shadow-slate-900/[0.06] ring-1 ring-slate-900/[0.035] transition-[box-shadow] duration-300 hover:shadow-xl hover:shadow-slate-900/[0.08] dark:border-slate-600/50 dark:bg-slate-900 dark:shadow-black/50 dark:ring-white/[0.06] dark:hover:shadow-black/55"
            @click="openChart('brand')"
        >
            <div class="pointer-events-none absolute inset-0 rounded-2xl dark:hidden" style="background-image: linear-gradient(135deg, rgb({{ $__dcRgb }} / 0.085) 0%, rgb({{ $__dcRgb }} / 0.03) 28%, rgb(255 255 255) 52%);"></div>
            <div class="pointer-events-none absolute inset-0 hidden rounded-2xl dark:block" style="background-image: linear-gradient(135deg, rgb({{ $__dcRgb }} / 0.24) 0%, rgb({{ $__dcRgb }} / 0.08) 38%, rgb(15 23 42 / 0.94) 62%, rgb(15 23 42 / 0.99) 100%);"></div>
            <div class="relative z-10 flex min-h-0 flex-1 flex-col overflow-hidden p-3">
            <h3 class="mb-2 shrink-0 text-xs font-semibold leading-tight text-gray-800 dark:text-white">NIC / Model</h3>
            <div class="flex min-h-0 flex-1 items-stretch gap-2 overflow-hidden">
                <div class="flex h-full min-h-0 shrink-0 items-center justify-center">
                <div
                    class="relative aspect-square h-[70%] min-h-[5.5rem] min-w-[5.5rem] max-h-[10rem] max-w-[min(100%,10rem)] w-auto rounded-full"
                    x-data="createDonut(@js($brandDonut['items']))"
                    @mousemove="onMove($event)"
                    @mouseleave="clear()"
                    style="background: conic-gradient({{ $brandDonut['gradient'] }});"
                >
                    <div class="absolute inset-[24%] rounded-full bg-white/95 dark:bg-slate-900"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-gray-700 dark:text-gray-50">
                        {{ $brandDonut['sum'] }}
                    </div>
                    <div x-show="hover" x-cloak class="pointer-events-none absolute z-20 rounded bg-gray-900/90 px-1.5 py-0.5 text-[10px] text-white"
                        :style="`left:${tipX}px; top:${tipY}px;`">
                        <span x-text="`${hover?.label}: ${hover?.total}`"></span>
                    </div>
                </div>
                </div>
                <div class="dc-drc-donut-legend grid min-h-0 min-w-0 flex-1 grid-flow-col grid-rows-[repeat(10,minmax(0,auto))] auto-cols-max gap-x-3 gap-y-0.5 overflow-x-auto overflow-y-hidden text-[10px] leading-tight">
                    @forelse ($brandDonut['items'] as $item)
                        <div
                            class="flex max-w-[9.5rem] items-start justify-between gap-1 sm:max-w-[11rem]">
                            <div class="flex min-w-0 flex-1 items-center gap-1">
                                <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                <span class="line-clamp-2 break-words text-gray-700 dark:text-slate-300" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            </div>
                            <span class="shrink-0 tabular-nums text-gray-500 dark:text-slate-400">{{ $item['total'] }}</span>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-slate-400">Belum ada data.</p>
                    @endforelse
                </div>
            </div>
            </div>
        </div>

        @php $__dcRgb = $dcInventoryCardAccents[2]['rgb']; @endphp
        <div
            class="dc-drc-donut-card relative flex h-full min-h-0 cursor-pointer flex-col overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-lg shadow-slate-900/[0.06] ring-1 ring-slate-900/[0.035] transition-[box-shadow] duration-300 hover:shadow-xl hover:shadow-slate-900/[0.08] dark:border-slate-600/50 dark:bg-slate-900 dark:shadow-black/50 dark:ring-white/[0.06] dark:hover:shadow-black/55"
            @click="openChart('vm_per_host')"
        >
            <div class="pointer-events-none absolute inset-0 rounded-2xl dark:hidden" style="background-image: linear-gradient(135deg, rgb({{ $__dcRgb }} / 0.085) 0%, rgb({{ $__dcRgb }} / 0.03) 28%, rgb(255 255 255) 52%);"></div>
            <div class="pointer-events-none absolute inset-0 hidden rounded-2xl dark:block" style="background-image: linear-gradient(135deg, rgb({{ $__dcRgb }} / 0.24) 0%, rgb({{ $__dcRgb }} / 0.08) 38%, rgb(15 23 42 / 0.94) 62%, rgb(15 23 42 / 0.99) 100%);"></div>
            <div class="relative z-10 flex min-h-0 flex-1 flex-col overflow-hidden p-3">
            <h3 class="mb-2 shrink-0 text-xs font-semibold leading-tight text-gray-800 dark:text-white">VM per VM Host</h3>
            <div class="flex min-h-0 flex-1 items-stretch gap-2 overflow-hidden">
                <div class="flex h-full min-h-0 shrink-0 items-center justify-center">
                <div
                    class="relative aspect-square h-[70%] min-h-[5.5rem] min-w-[5.5rem] max-h-[10rem] max-w-[min(100%,10rem)] w-auto rounded-full"
                    x-data="createDonut(@js($vmPerHostDonut['items']))"
                    @mousemove="onMove($event)"
                    @mouseleave="clear()"
                    style="background: conic-gradient({{ $vmPerHostDonut['gradient'] }});"
                >
                    <div class="absolute inset-[24%] rounded-full bg-white/95 dark:bg-slate-900"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-gray-700 dark:text-gray-50">
                        {{ $vmPerHostDonut['sum'] }}
                    </div>
                    <div x-show="hover" x-cloak class="pointer-events-none absolute z-20 rounded bg-gray-900/90 px-1.5 py-0.5 text-[10px] text-white"
                        :style="`left:${tipX}px; top:${tipY}px;`">
                        <span x-text="`${hover?.label}: ${hover?.total}`"></span>
                    </div>
                </div>
                </div>
                <div class="dc-drc-donut-legend grid min-h-0 min-w-0 flex-1 grid-flow-col grid-rows-[repeat(10,minmax(0,auto))] auto-cols-max gap-x-3 gap-y-0.5 overflow-x-auto overflow-y-hidden text-[10px] leading-tight">
                    @forelse ($vmPerHostDonut['items'] as $item)
                        <div
                            class="flex max-w-[9.5rem] items-start justify-between gap-1 sm:max-w-[11rem]">
                            <div class="flex min-w-0 flex-1 items-center gap-1">
                                <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                <span class="line-clamp-2 break-words text-gray-700 dark:text-slate-300" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            </div>
                            <span class="shrink-0 tabular-nums text-gray-500 dark:text-slate-400">{{ $item['total'] }}</span>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-slate-400">Belum ada VM host.</p>
                    @endforelse
                </div>
            </div>
            </div>
        </div>

        @php $__dcRgb = $dcInventoryCardAccents[3]['rgb']; @endphp
        <div
            class="dc-drc-donut-card relative flex h-full min-h-0 cursor-pointer flex-col overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-lg shadow-slate-900/[0.06] ring-1 ring-slate-900/[0.035] transition-[box-shadow] duration-300 hover:shadow-xl hover:shadow-slate-900/[0.08] dark:border-slate-600/50 dark:bg-slate-900 dark:shadow-black/50 dark:ring-white/[0.06] dark:hover:shadow-black/55"
            @click="openChart('os')"
        >
            <div class="pointer-events-none absolute inset-0 rounded-2xl dark:hidden" style="background-image: linear-gradient(135deg, rgb({{ $__dcRgb }} / 0.085) 0%, rgb({{ $__dcRgb }} / 0.03) 28%, rgb(255 255 255) 52%);"></div>
            <div class="pointer-events-none absolute inset-0 hidden rounded-2xl dark:block" style="background-image: linear-gradient(135deg, rgb({{ $__dcRgb }} / 0.24) 0%, rgb({{ $__dcRgb }} / 0.08) 38%, rgb(15 23 42 / 0.94) 62%, rgb(15 23 42 / 0.99) 100%);"></div>
            <div class="relative z-10 flex min-h-0 flex-1 flex-col overflow-hidden p-3">
            <h3 class="mb-2 shrink-0 text-xs font-semibold leading-tight text-gray-800 dark:text-white">Sistem operasi</h3>
            <div class="flex min-h-0 flex-1 items-stretch gap-2 overflow-hidden">
                <div class="flex h-full min-h-0 shrink-0 items-center justify-center">
                <div
                    class="relative aspect-square h-[70%] min-h-[5.5rem] min-w-[5.5rem] max-h-[10rem] max-w-[min(100%,10rem)] w-auto rounded-full"
                    x-data="createDonut(@js($osDonut['items']))"
                    @mousemove="onMove($event)"
                    @mouseleave="clear()"
                    style="background: conic-gradient({{ $osDonut['gradient'] }});"
                >
                    <div class="absolute inset-[24%] rounded-full bg-white/95 dark:bg-slate-900"></div>
                    <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-gray-700 dark:text-gray-50">
                        {{ $osDonut['sum'] }}
                    </div>
                    <div x-show="hover" x-cloak class="pointer-events-none absolute z-20 rounded bg-gray-900/90 px-1.5 py-0.5 text-[10px] text-white"
                        :style="`left:${tipX}px; top:${tipY}px;`">
                        <span x-text="`${hover?.label}: ${hover?.total}`"></span>
                    </div>
                </div>
                </div>
                <div class="dc-drc-donut-legend grid min-h-0 min-w-0 flex-1 grid-flow-col grid-rows-[repeat(10,minmax(0,auto))] auto-cols-max gap-x-3 gap-y-0.5 overflow-x-auto overflow-y-hidden text-[10px] leading-tight">
                    @forelse ($osDonut['items'] as $item)
                        <div
                            class="flex max-w-[9.5rem] items-start justify-between gap-1 sm:max-w-[11rem]">
                            <div class="flex min-w-0 flex-1 items-center gap-1">
                                <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                <span class="line-clamp-2 break-words text-gray-700 dark:text-slate-300" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            </div>
                            <span class="shrink-0 tabular-nums text-gray-500 dark:text-slate-400">{{ $item['total'] }}</span>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-slate-400">Belum ada data OS.</p>
                    @endforelse
                </div>
            </div>
            </div>
        </div>
    </div>

    <template x-teleport="body">
        <div
            x-show="showChartModal"
            x-cloak
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 p-4"
            @click.self="showChartModal = false"
        >
            <div
                class="relative max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-gray-200/90 bg-white p-6 shadow-2xl shadow-slate-900/20 ring-1 ring-slate-900/[0.05] dark:border-slate-600/50 dark:bg-slate-900 dark:shadow-black/60 dark:ring-white/[0.06]"
                @click.stop
            >
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-lg font-semibold text-gray-800 dark:text-white" x-text="currentChart()?.title ?? 'Detail Chart'"></h4>
                <button type="button" @click="showChartModal = false"
                    class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">
                    Tutup
                </button>
            </div>
            <template x-if="currentChart()">
                <div class="flex flex-col items-stretch gap-6 sm:flex-row sm:items-center">
                    <div class="relative mx-auto h-72 w-72 shrink-0 rounded-full sm:mx-0"
                        x-data="createDonut(currentChart()?.items || [])"
                        x-effect="setItems(currentChart()?.items || [])"
                        @mousemove="onMove($event)"
                        @mouseleave="clear()"
                        :style="`background: conic-gradient(${gradient});`">
                        <div class="absolute inset-[22%] rounded-full bg-white/95 dark:bg-slate-900"></div>
                        <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-gray-700 dark:text-gray-50"
                            x-text="`${currentChart()?.sum ?? 0} ${currentChart()?.unit ?? 'Unit'}`"></div>
                        <div x-show="hover" x-cloak class="pointer-events-none absolute z-20 rounded-md bg-gray-900/90 px-2 py-1 text-[11px] text-white"
                            :style="`left:${tipX}px; top:${tipY}px;`">
                            <span x-text="`${hover?.label}: ${hover?.total}`"></span>
                        </div>
                    </div>
                    <div class="min-h-0 min-w-0 flex-1 space-y-2">
                        <template x-for="(item, idx) in (currentChart()?.items || [])" :key="idx + '-' + (item.label ?? '')">
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background-color:${item.color}`"></span>
                                    <span class="truncate text-gray-700 dark:text-slate-300" x-text="item.label"></span>
                                </div>
                                <span class="shrink-0 text-gray-500 dark:text-slate-400" x-text="`${item.total} (${item.percent}%)`"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
            </div>
        </div>
    </template>
</div>
