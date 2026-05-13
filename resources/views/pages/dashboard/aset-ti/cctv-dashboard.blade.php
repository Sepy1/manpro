@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Aset TI - Dashboard CCTV" />
    @php
        $brandPalette = ['#06b6d4', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#22c55e', '#a855f7', '#14b8a6', '#f97316'];
        $monitorPalette = ['#10b981', '#ef4444'];

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

        $brandDonut = $buildDonut($brandLabels->all(), $brandTotals->all(), $brandPalette);
        $monitorDonut = $buildDonut($monitorLabels, $monitorTotals, $monitorPalette);
        $connectionDonut = $buildDonut($connectionLabels->all(), $connectionTotals->all(), ['#3b82f6', '#ef4444', '#8b5cf6', '#14b8a6', '#f59e0b']);
        $harddiskDonut = $buildDonut($harddiskLabels->all(), $harddiskTotals->all(), ['#06b6d4', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444']);
        $channelDonut = $buildDonut($channelLabels->all(), $channelTotals->all(), ['#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6']);
    @endphp

    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Statistik Merk DVR</h3>
                    <div class="flex items-center gap-6">
                        <div class="relative h-44 w-44 shrink-0 rounded-full"
                            style="background: conic-gradient({{ $brandDonut['gradient'] }});">
                            <div class="absolute inset-[22%] rounded-full bg-white dark:bg-gray-900"></div>
                            <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200">
                                {{ $brandDonut['sum'] }} Unit
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
                                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data merk DVR.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Statistik Harddisk</h3>
                    <div class="flex items-center gap-6">
                        <div class="relative h-44 w-44 shrink-0 rounded-full"
                            style="background: conic-gradient({{ $harddiskDonut['gradient'] }});">
                            <div class="absolute inset-[22%] rounded-full bg-white dark:bg-gray-900"></div>
                            <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200">
                                {{ $harddiskDonut['sum'] }} Kantor
                            </div>
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            @forelse ($harddiskDonut['items'] as $item)
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                        <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    </div>
                                    <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $item['total'] }} ({{ $item['percent'] }}%)</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data harddisk.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <div class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Monitor / TV</h3>
                    <div class="flex items-center gap-6">
                        <div class="relative h-44 w-44 shrink-0 rounded-full"
                            style="background: conic-gradient({{ $monitorDonut['gradient'] }});">
                            <div class="absolute inset-[22%] rounded-full bg-white dark:bg-gray-900"></div>
                            <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200">
                                {{ $monitorDonut['sum'] }} Kantor
                            </div>
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            @forelse ($monitorDonut['items'] as $item)
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                        <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    </div>
                                    <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $item['total'] }} ({{ $item['percent'] }}%)</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data monitor.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Statistik Jumlah Channel</h3>
                    <div class="flex items-center gap-6">
                        <div class="relative h-44 w-44 shrink-0 rounded-full"
                            style="background: conic-gradient({{ $channelDonut['gradient'] }});">
                            <div class="absolute inset-[22%] rounded-full bg-white dark:bg-gray-900"></div>
                            <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200">
                                {{ $channelDonut['sum'] }} Kantor
                            </div>
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            @forelse ($channelDonut['items'] as $item)
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                        <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    </div>
                                    <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $item['total'] }} ({{ $item['percent'] }}%)</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data channel.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="mb-3 text-base font-semibold text-gray-800 dark:text-white/90">Statistik Cloud</h3>
                <div class="flex items-center gap-6">
                    <div class="relative h-44 w-44 shrink-0 rounded-full"
                        style="background: conic-gradient({{ $connectionDonut['gradient'] }});">
                        <div class="absolute inset-[22%] rounded-full bg-white dark:bg-gray-900"></div>
                        <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-700 dark:text-gray-200">
                            {{ $connectionDonut['sum'] }} Kantor
                        </div>
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        @forelse ($connectionDonut['items'] as $item)
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $item['color'] }}"></span>
                                    <span class="truncate text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                </div>
                                <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $item['total'] }} ({{ $item['percent'] }}%)</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada data koneksi.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Daftar Kantor Yang Belum Melakukan Update Data
                </h3>
                <a href="{{ route('admin.aset-ti.cctv.dashboard.export-missing') }}" data-no-transition
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg border border-blue-500 px-3 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:border-blue-400 dark:text-blue-300 dark:hover:bg-blue-900/20">
                    Export Excel
                </a>
            </div>

            <div class="max-h-[300px] overflow-x-auto overflow-y-auto">
                <table class="min-w-full table-fixed border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="w-[24%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cabang</th>
                            <th class="w-[24%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kantor Kas</th>
                            <th class="w-[16%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Monitor</th>
                            <th class="w-[16%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Koneksi</th>
                            <th class="w-[20%] px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($missingDvrRows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->branch }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->office }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->monitor ?: '-' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->connection_status ?: '-' }}</td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Tidak ada data kantor/kas yang nama DVR-nya kosong.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-800">
                {{ $missingDvrRows->links() }}
            </div>
        </div>
    </div>
@endsection
