{{-- Kartu dengan gradien aksen tipis (selaras Data Center / Inventaris DC-DRC) --}}
@props([
    'accentIndex' => 0,
    'padding' => 'p-5',
    /** hidden: potong konten + radius; visible: cocok untuk shell halaman dengan tabel/scroll panjang */
    'shellOverflow' => 'hidden',
])

@php
    $dashboardAccentRgbList = [
        ['rgb' => '139 92 246'],
        ['rgb' => '59 130 246'],
        ['rgb' => '217 70 239'],
        ['rgb' => '52 211 153'],
        ['rgb' => '14 165 233'],
        ['rgb' => '251 146 60'],
    ];
    $accentRgb = $dashboardAccentRgbList[(int) $accentIndex % count($dashboardAccentRgbList)]['rgb'];
    $overflowClass = $shellOverflow === 'visible' ? 'overflow-visible' : 'overflow-hidden';
@endphp

<div {{ $attributes->merge([
    'class' => 'relative '.$overflowClass.' rounded-2xl border border-gray-200/90 bg-white shadow-lg shadow-slate-900/[0.06] ring-1 ring-slate-900/[0.035] transition-[box-shadow] duration-300 hover:shadow-xl hover:shadow-slate-900/[0.08] dark:border-slate-600/50 dark:bg-slate-900 dark:shadow-black/50 dark:ring-white/[0.06] dark:hover:shadow-black/55',
]) }}>
    <div class="pointer-events-none absolute inset-0 rounded-2xl dark:hidden" style="background-image: linear-gradient(135deg, rgb({{ $accentRgb }} / 0.085) 0%, rgb({{ $accentRgb }} / 0.03) 28%, rgb(255 255 255) 52%);"></div>
    <div class="pointer-events-none absolute inset-0 hidden rounded-2xl dark:block" style="background-image: linear-gradient(135deg, rgb({{ $accentRgb }} / 0.24) 0%, rgb({{ $accentRgb }} / 0.08) 38%, rgb(15 23 42 / 0.94) 62%, rgb(15 23 42 / 0.99) 100%);"></div>
    <div @class(['relative z-10 flex min-h-0 flex-1 flex-col', $padding])>
        {{ $slot }}
    </div>
</div>
