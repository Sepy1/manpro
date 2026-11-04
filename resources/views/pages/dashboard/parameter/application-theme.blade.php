@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Tema Aplikasi" />

    <div class="mx-auto min-h-0 w-full max-w-6xl overflow-y-auto pb-2">
        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-800 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <x-dashboard.accent-card accent-index="4" padding="p-5 lg:p-7">
            <div class="mb-6 max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-400">Personalisasi antarmuka</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Pilih tema warna aplikasi</h2>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    Pilih tema gelap atau terang. Setiap palet menggunakan kombinasi teks dan permukaan berkontras tinggi agar informasi tetap nyaman dibaca.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.parameter.application-theme.update') }}" x-data="{
                selected: @js(old('theme', $activeTheme)),
                selectedFont: @js(old('font_family', $activeFontFamily)),
                selectedSize: @js(old('font_size', $activeFontSize)),
                selectedWeight: @js(old('font_weight', $activeFontWeight))
            }">
                @csrf
                @method('PUT')

                <div class="mb-6 grid grid-cols-1 gap-4 rounded-2xl border border-gray-200 bg-gray-50/70 p-4 md:grid-cols-2 dark:border-white/10 dark:bg-black/10 lg:grid-cols-3 lg:p-5">
                    <div>
                        <label for="font_family" class="mb-2 block text-sm font-semibold text-gray-800 dark:text-gray-200">Jenis font aplikasi</label>
                        <select id="font_family" name="font_family" x-model="selectedFont"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                            @foreach ($fontFamilies as $key => $font)
                                <option value="{{ $key }}">{{ $font['name'] }} — {{ $font['description'] }}</option>
                            @endforeach
                        </select>
                        @error('font_family')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300"
                            :style="{ fontFamily: {
                                figtree: 'Figtree, Inter, system-ui, sans-serif',
                                system: 'system-ui, sans-serif',
                                segoe: 'Segoe UI, Arial, sans-serif',
                                arial: 'Arial, sans-serif',
                                georgia: 'Georgia, serif'
                            }[selectedFont] }">
                            Contoh tampilan: Informasi aplikasi mudah dibaca.
                        </p>
                    </div>

                    <div>
                        <label for="font_size" class="mb-2 block text-sm font-semibold text-gray-800 dark:text-gray-200">Ukuran font aplikasi</label>
                        <select id="font_size" name="font_size" x-model="selectedSize"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                            @foreach ($fontSizes as $key => $size)
                                <option value="{{ $key }}">{{ $size['name'] }} — {{ $size['description'] }}</option>
                            @endforeach
                        </select>
                        @error('font_size')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <div class="mt-2 flex items-baseline gap-2 text-gray-700 dark:text-gray-300">
                            <span class="font-semibold" :style="{ fontSize: { compact: '12px', normal: '13px', comfortable: '14px', large: '15.2px' }[selectedSize] }">Aa</span>
                            <span class="text-xs">Preview ukuran teks</span>
                        </div>
                    </div>

                    <div>
                        <label for="font_weight" class="mb-2 block text-sm font-semibold text-gray-800 dark:text-gray-200">Ketebalan font aplikasi</label>
                        <select id="font_weight" name="font_weight" x-model="selectedWeight"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                            @foreach ($fontWeights as $key => $weight)
                                <option value="{{ $key }}">{{ $weight['name'] }} — {{ $weight['description'] }}</option>
                            @endforeach
                        </select>
                        @error('font_weight')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300"
                            :style="{ fontWeight: { normal: 400, bold: 700 }[selectedWeight] }">
                            Contoh ketebalan teks aplikasi
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($themes as $key => $theme)
                        <label class="group relative cursor-pointer overflow-hidden rounded-2xl border p-1 transition duration-200 focus-within:ring-2 focus-within:ring-brand-400"
                            :class="selected === '{{ $key }}' ? 'border-brand-400 shadow-lg shadow-black/30' : 'border-white/10 hover:-translate-y-0.5 hover:border-white/25'">
                            <input type="radio" name="theme" value="{{ $key }}" x-model="selected" class="sr-only" />
                            <div class="rounded-[0.85rem] p-4" style="background: linear-gradient(145deg, {{ $theme['colors'][0] }}, {{ $theme['colors'][1] }});">
                                <div class="mb-8 flex items-center justify-between">
                                    <div class="flex gap-1.5">
                                        @foreach ($theme['colors'] as $color)
                                            <span class="h-3.5 w-3.5 rounded-full border border-white/20" style="background-color: {{ $color }}"></span>
                                        @endforeach
                                    </div>
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white"
                                        x-show="selected === '{{ $key }}'" x-cloak aria-hidden="true">✓</span>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-black/15 p-3 backdrop-blur-sm">
                                    <div class="mb-2 h-2 w-20 rounded-full" style="background-color: {{ $theme['colors'][2] }}"></div>
                                    <div class="h-1.5 w-full rounded-full bg-white/70"></div>
                                    <div class="mt-1.5 h-1.5 w-2/3 rounded-full bg-white/30"></div>
                                </div>
                            </div>
                            <div class="px-3 pb-3 pt-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $theme['name'] }}</h3>
                                        <span class="rounded-full border border-gray-300 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-gray-600 dark:border-white/15 dark:text-gray-300">{{ $theme['mode'] }}</span>
                                    </div>
                                    @if ($key === $activeTheme)
                                        <span class="rounded-full bg-brand-500/15 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-brand-300">Aktif</span>
                                    @endif
                                </div>
                                <p class="mt-1.5 text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $theme['description'] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>

                @error('theme')
                    <p class="mt-4 text-sm text-red-400">{{ $message }}</p>
                @enderror

                <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-white/10 pt-5">
                    <button type="submit" class="inline-flex h-11 items-center rounded-xl bg-brand-500 px-6 text-sm font-semibold text-white shadow-lg shadow-black/20 transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2 focus:ring-offset-slate-900">
                        Terapkan tema
                    </button>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Tema berlaku untuk seluruh pengguna aplikasi.</p>
                </div>
            </form>
        </x-dashboard.accent-card>
    </div>
@endsection
