@extends('layouts.admin')

@section('admin-content')
    <div class="flex h-full min-h-0 flex-col gap-4" x-data="attackMap(@js(route('admin.attack-map.data')), @js($initialFilters))" x-init="init()">
        <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-end sm:justify-between sm:gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-400">Threat intelligence</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Attack Map</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Peta sumber dan tujuan serangan berdasarkan Log Security.</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
            <template x-for="item in cards" :key="item.label">
                <div class="content-card-tight px-4 py-3"><p class="text-xs text-gray-500 dark:text-gray-400" x-text="item.label"></p><p class="mt-1 text-xl font-bold text-gray-900 dark:text-white" x-text="item.value"></p></div>
            </template>
        </div>

        <div x-show="geolocation.message" x-cloak class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700/70 dark:bg-amber-950/40 dark:text-amber-200">
            <span class="font-semibold">Geolokasi IP:</span>
            <span x-text="geolocation.message"></span>
            <span x-show="geolocation.unresolved" class="ml-1" x-text="`(${geolocation.unresolved} IP belum terpetakan)`"></span>
            <div x-show="geolocation.unresolved_ips?.length" class="mt-2 flex flex-wrap gap-1.5">
                <template x-for="ip in (geolocation.unresolved_ips || [])" :key="ip">
                    <code class="rounded-md border border-amber-300/70 bg-white/70 px-2 py-1 text-xs font-semibold text-amber-900 dark:border-amber-700 dark:bg-slate-950/50 dark:text-amber-100" x-text="ip"></code>
                </template>
            </div>
        </div>

        <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,1fr)_260px]">
            <div class="flex min-h-[430px] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-gray-200 p-2 sm:p-3 dark:border-gray-800">
                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-red-500"></i> Critical</span>
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-orange-500"></i> High</span>
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-yellow-400"></i> Medium</span>
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-sky-400"></i> Low</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex h-9 items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <i class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></i> Realtime
                        </span>
                        <button type="button" @click="openPeriodModal()"
                            class="inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-xs font-semibold"
                            :class="appliedDateFrom || appliedDateUntil ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300' : 'border-gray-300 text-gray-600 dark:border-gray-700 dark:text-gray-300'">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" />
                            </svg>
                            <span x-text="appliedDateFrom || appliedDateUntil ? 'Periode aktif' : 'Periode'"></span>
                        </button>
                        <select x-model="hours" @change="load()" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700 dark:text-white">
                            <option value="1">1 jam</option><option value="6">6 jam</option><option value="12">12 jam</option>
                            <option value="24">24 jam</option><option value="72">3 hari</option><option value="168">7 hari</option>
                        </select>
                        <select x-model="severity" @change="load()" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Semua severity</option>
                            @foreach ($severities as $severity)<option value="{{ $severity }}">{{ $severity }}</option>@endforeach
                        </select>
                        <button type="button" @click="load()" class="h-9 rounded-lg bg-brand-500 px-4 text-xs font-medium text-white">Refresh</button>
                        <label class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 text-xs font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">
                            <input type="checkbox" x-model="showSourceLabels" @change="draw()"
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span>Label SRC</span>
                        </label>
                        <label class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 text-xs font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">
                            <input type="checkbox" x-model="showDestinationLabels" @change="draw()"
                                class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span>Label DST</span>
                        </label>
                    </div>
                </div>
                <div class="relative min-h-[360px] flex-1 overflow-hidden bg-slate-100 dark:bg-slate-950">
                    <div id="attack-world-map" class="absolute inset-0 z-0 h-full w-full"></div>
                    <div x-show="loading" class="absolute inset-0 z-[1000] flex items-center justify-center bg-white/45 backdrop-blur-[1px] dark:bg-slate-950/45">
                        <span class="h-9 w-9 animate-spin rounded-full border-4 border-gray-400/25 border-t-brand-500"></span>
                    </div>
                    <div x-show="mapError" x-cloak class="absolute inset-0 z-[1001] flex items-center justify-center bg-slate-100/95 p-6 text-center dark:bg-slate-950/95">
                        <div class="max-w-md rounded-xl border border-red-300 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200">
                            <p class="font-semibold">Peta gagal dimuat</p>
                            <p class="mt-1" x-text="mapError"></p>
                            <button type="button" @click="retryMap()" class="mt-3 rounded-lg bg-brand-500 px-4 py-2 text-xs font-semibold text-white">Coba lagi</button>
                        </div>
                    </div>
                    <div class="pointer-events-none absolute bottom-3 left-3 z-[900] rounded-lg bg-slate-950/80 px-3 py-2 text-[11px] text-slate-200">
                        Garis menunjukkan arah source → destination · IP private diarahkan ke Semarang
                    </div>
                </div>
            </div>
            <aside class="content-card min-h-0 overflow-auto p-4">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 pb-3 dark:border-gray-800">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Top negara sumber</h2>
                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                        <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                            :checked="allCountriesEnabled" @change="toggleAllCountries($event.target.checked)">
                        <span x-text="allCountriesEnabled ? 'Disable All' : 'Enable All'"></span>
                    </label>
                </div>
                <div class="mt-3 space-y-2">
                    <template x-for="(country, index) in countries" :key="country.country">
                        <div class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                            <label class="flex min-w-0 cursor-pointer items-center gap-2">
                                <input type="checkbox" class="h-4 w-4 shrink-0 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                    :checked="isCountryEnabled(country.country)"
                                    @change="toggleCountry(country.country, $event.target.checked)">
                                <span class="truncate"><span class="mr-2 text-gray-400" x-text="index + 1"></span><span x-text="country.country"></span></span>
                            </label>
                            <strong x-text="country.count.toLocaleString('id-ID')"></strong>
                        </div>
                    </template>
                    <p x-show="!loading && countries.length === 0" class="py-8 text-center text-sm text-gray-500">Belum ada lokasi yang dapat dipetakan.</p>
                </div>
                <p class="mt-4 border-t border-gray-200 pt-3 text-[10px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <a href="https://ipdata.co" target="_blank" rel="noopener noreferrer" class="hover:text-brand-500">IP geolocation by ipdata.co</a>
                </p>
            </aside>
        </div>

        <div x-show="showPeriodModal" x-cloak @keydown.escape.window="showPeriodModal = false"
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
            @click.self="showPeriodModal = false">
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Periode Log Security</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tampilkan serangan berdasarkan tanggal event.</p>
                    </div>
                    <button type="button" @click="showPeriodModal = false" class="rounded-lg px-2 py-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">&times;</button>
                </div>
                <div class="mt-5">
                    <input x-ref="periodInput" type="text" class="sr-only" tabindex="-1" aria-hidden="true">
                    <div x-ref="periodCalendar" class="flex min-h-[310px] justify-center"></div>
                    <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-center text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <span x-show="!dateFrom">Pilih tanggal awal, lalu pilih tanggal akhir.</span>
                        <span x-show="dateFrom && !dateUntil"><b x-text="dateFrom"></b> — pilih tanggal akhir.</span>
                        <span x-show="dateFrom && dateUntil"><b x-text="dateFrom"></b> sampai <b x-text="dateUntil"></b></span>
                    </div>
                </div>
                <p x-show="periodError" x-text="periodError" class="mt-3 text-xs font-medium text-red-600 dark:text-red-400"></p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="resetPeriod()" class="h-10 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</button>
                    <button type="button" @click="applyPeriod()" class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Terapkan</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <style>
        #attack-world-map { background:#dbeafe; }
        #attack-world-map .attack-arc { animation:attack-dash 2.2s linear infinite; }
        #attack-world-map .attack-location-marker { background:transparent; border:0; }
        #attack-world-map .attack-location-dot {
            position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); border:2px solid #fff;
            border-radius:9999px; box-shadow:0 1px 5px rgba(15,23,42,.4);
        }
        #attack-world-map .attack-location-label {
            position:absolute; left:50%; z-index:1; transform:translateX(-50%); border:1px solid rgba(255,255,255,.7);
            border-radius:6px; box-shadow:0 2px 8px rgba(15,23,42,.22); color:#fff; font-size:10px;
            font-weight:700; line-height:1.2; padding:3px 6px; pointer-events:none; white-space:nowrap;
        }
        #attack-world-map .attack-source-marker .attack-location-label { bottom:calc(100% + 2px); background:rgba(15,23,42,.86); }
        #attack-world-map .attack-destination-marker .attack-location-label { top:calc(100% + 2px); background:rgba(8,145,178,.9); }
        .dark .flatpickr-calendar { background:#111827; border-color:#374151; box-shadow:0 12px 30px rgba(0,0,0,.35); }
        .dark .flatpickr-months .flatpickr-month,
        .dark .flatpickr-current-month .flatpickr-monthDropdown-months,
        .dark .flatpickr-current-month input.cur-year,
        .dark span.flatpickr-weekday { background:#111827; color:#e5e7eb; }
        .dark .flatpickr-day { color:#d1d5db; }
        .dark .flatpickr-day.prevMonthDay,
        .dark .flatpickr-day.nextMonthDay { color:#6b7280; }
        .dark .flatpickr-day:hover { background:#1f2937; border-color:#374151; }
        .flatpickr-day.inRange {
            background:#dbeafe !important; border-color:#bfdbfe !important; color:#1e3a8a !important;
            box-shadow:-5px 0 0 #dbeafe,5px 0 0 #dbeafe !important;
        }
        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange,
        .flatpickr-day.selected:hover,
        .flatpickr-day.startRange:hover,
        .flatpickr-day.endRange:hover {
            background:#2563eb !important; border-color:#2563eb !important; color:#fff !important;
            box-shadow:none !important;
        }
        .dark .flatpickr-day.inRange {
            background:#1e3a8a !important; border-color:#1d4ed8 !important; color:#dbeafe !important;
            box-shadow:-5px 0 0 #1e3a8a,5px 0 0 #1e3a8a !important;
        }
        .dark .flatpickr-day.selected,
        .dark .flatpickr-day.startRange,
        .dark .flatpickr-day.endRange { background:#2563eb !important; border-color:#60a5fa !important; color:#fff !important; }
        @keyframes attack-dash { to { stroke-dashoffset:-28; } }
    </style>
    <script>
        function attackMap(endpoint, initialFilters = {}) {
            return {
                endpoint, map: null, layer: null, canvasRenderer: null, timer: null, mapInitTimer: null, resizeTimers: [], request: null, visibilityHandler: null, datePicker: null, destroyed: false, loading: false, mapError: '', lastFingerprint: null,
                filters: initialFilters, hours: initialFilters.hours || '24', severity: initialFilters.severity || '', showPeriodModal: false, dateFrom: initialFilters.date_from || '', dateUntil: initialFilters.date_until || '', appliedDateFrom: initialFilters.date_from || '', appliedDateUntil: initialFilters.date_until || '', periodError: '', showSourceLabels: true, showDestinationLabels: true, countries: [], arcs: [], enabledCountries: {}, geolocation: { message: null, unresolved: 0, unresolved_ips: [] }, summary: { total: 0, sources: 0, countries: 0, blocked: 0 },
                get cards() { return [
                    { label: 'Total event', value: this.summary.total.toLocaleString('id-ID') },
                    { label: 'Source IP', value: this.summary.sources.toLocaleString('id-ID') },
                    { label: 'Negara sumber', value: this.summary.countries.toLocaleString('id-ID') },
                    { label: 'Diblokir', value: this.summary.blocked.toLocaleString('id-ID') },
                ]; },
                get allCountriesEnabled() {
                    return this.countries.length > 0 && this.countries.every(country => this.isCountryEnabled(country.country));
                },
                init() {
                    this.destroyed = false;
                    this.initializeMap(0);
                    this.$nextTick(() => this.initializePeriodCalendar());
                },
                initializePeriodCalendar() {
                    if (this.datePicker || typeof window.flatpickr !== 'function' || !this.$refs.periodInput) return;
                    const defaults = [this.dateFrom, this.dateUntil].filter(Boolean);
                    this.datePicker = window.flatpickr(this.$refs.periodInput, {
                        mode: 'range',
                        inline: true,
                        appendTo: this.$refs.periodCalendar,
                        dateFormat: 'Y-m-d',
                        defaultDate: defaults,
                        locale: window.flatpickrIndonesian || 'default',
                        monthSelectorType: 'static',
                        onChange: (selectedDates, _, instance) => {
                            this.dateFrom = selectedDates[0] ? instance.formatDate(selectedDates[0], 'Y-m-d') : '';
                            this.dateUntil = selectedDates[1] ? instance.formatDate(selectedDates[1], 'Y-m-d') : '';
                            this.periodError = selectedDates.length === 1 ? 'Pilih satu tanggal lagi sebagai tanggal akhir.' : '';
                        },
                    });
                },
                initializeMap(attempt = 0) {
                    if (this.destroyed || this.map) return;
                    if (typeof L === 'undefined') {
                        if (attempt >= 50) {
                            this.mapError = 'Library peta belum tersedia. Periksa koneksi ke unpkg.com lalu tekan Coba lagi.';
                            return;
                        }
                        clearTimeout(this.mapInitTimer);
                        this.mapInitTimer = setTimeout(() => this.initializeMap(attempt + 1), 100);
                        return;
                    }
                    this.$nextTick(() => requestAnimationFrame(() => {
                        if (this.destroyed || this.map || !document.getElementById('attack-world-map')) return;
                        this.mapError = '';
                        this.map = L.map('attack-world-map', {
                            worldCopyJump: true,
                            minZoom: 2,
                            zoomAnimation: false,
                            fadeAnimation: false,
                            markerZoomAnimation: false,
                            wheelDebounceTime: 16,
                            wheelPxPerZoomLevel: 80,
                        }).setView([12, 20], 2);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 8, attribution: '&copy; OpenStreetMap' }).addTo(this.map);
                        this.canvasRenderer = L.canvas({ padding: .5, tolerance: 8 });
                        this.layer = L.layerGroup().addTo(this.map);
                        [0, 150, 400].forEach(delay => this.resizeTimers.push(setTimeout(() => this.map?.invalidateSize(false), delay)));
                        this.load();
                        this.timer = setInterval(() => { if (!document.hidden) this.load(false); }, 3000);
                        this.visibilityHandler = () => {
                            if (!document.hidden) {
                                this.map?.invalidateSize(false);
                                this.load(false);
                            }
                        };
                        document.addEventListener('visibilitychange', this.visibilityHandler);
                    }));
                },
                retryMap() {
                    this.mapError = '';
                    this.initializeMap(0);
                },
                destroy() {
                    this.destroyed = true;
                    clearInterval(this.timer);
                    clearTimeout(this.mapInitTimer);
                    this.resizeTimers.forEach(timer => clearTimeout(timer));
                    if (this.visibilityHandler) document.removeEventListener('visibilitychange', this.visibilityHandler);
                    this.datePicker?.destroy();
                    this.request?.abort(); this.map?.remove();
                },
                escape(value) {
                    return String(value ?? '-').replace(/[&<>'"]/g, character => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[character]));
                },
                locationIcon(label, color, type, diameter, showLabel = true) {
                    return L.divIcon({
                        className: `attack-location-marker attack-${type}-marker`,
                        html: `<span class="attack-location-dot" style="width:${diameter}px;height:${diameter}px;background:${color}"></span>${showLabel ? `<span class="attack-location-label">${this.escape(label)}</span>` : ''}`,
                        iconSize: [18, 18],
                        iconAnchor: [9, 9],
                    });
                },
                color(severity) {
                    const value = String(severity).toLowerCase();
                    if (value.includes('critical')) return '#ef4444';
                    if (value.includes('high')) return '#f97316';
                    if (value.includes('medium')) return '#facc15';
                    return '#38bdf8';
                },
                isCountryEnabled(country) {
                    return this.enabledCountries[country] !== false;
                },
                toggleCountry(country, enabled) {
                    this.enabledCountries = { ...this.enabledCountries, [country]: enabled };
                    this.draw();
                },
                toggleAllCountries(enabled) {
                    this.enabledCountries = Object.fromEntries(this.countries.map(country => [country.country, enabled]));
                    this.draw();
                },
                openPeriodModal() {
                    this.showPeriodModal = true;
                    this.$nextTick(() => this.datePicker?.redraw());
                },
                applyPeriod() {
                    if (!this.dateFrom || !this.dateUntil) {
                        this.periodError = 'Pilih tanggal awal dan tanggal akhir pada kalender.';
                        return;
                    }
                    if (this.dateFrom && this.dateUntil && this.dateFrom > this.dateUntil) {
                        this.periodError = 'Tanggal akhir tidak boleh lebih kecil dari tanggal awal.';
                        return;
                    }
                    this.periodError = '';
                    this.appliedDateFrom = this.dateFrom;
                    this.appliedDateUntil = this.dateUntil;
                    this.filters = { ...this.filters, date_from: this.dateFrom, date_until: this.dateUntil };
                    this.showPeriodModal = false;
                    this.load();
                },
                resetPeriod() {
                    this.dateFrom = '';
                    this.dateUntil = '';
                    this.appliedDateFrom = '';
                    this.appliedDateUntil = '';
                    this.periodError = '';
                    this.datePicker?.clear(false);
                    this.filters = { ...this.filters, date_from: '', date_until: '' };
                    this.showPeriodModal = false;
                    this.load();
                },
                curve(source, destination) {
                    const x1 = Number(source.longitude), x2 = Number(destination.longitude);
                    const y1 = Number(source.latitude), y2 = Number(destination.latitude);
                    const mx = (x1 + x2) / 2, my = (y1 + y2) / 2;
                    const distance = Math.hypot(x2 - x1, y2 - y1) || 1;
                    const cx = mx - ((y2 - y1) / distance) * Math.min(28, distance * .28);
                    const cy = my + ((x2 - x1) / distance) * Math.min(28, distance * .28);
                    return Array.from({ length: 31 }, (_, i) => {
                        const t = i / 30, u = 1 - t;
                        const lng = u*u*x1 + 2*u*t*cx + t*t*x2;
                        return [u*u*y1 + 2*u*t*cy + t*t*y2, lng];
                    });
                },
                draw() {
                    const layer = window.Alpine?.raw ? window.Alpine.raw(this.layer) : this.layer;
                    const renderer = window.Alpine?.raw ? window.Alpine.raw(this.canvasRenderer) : this.canvasRenderer;
                    layer.clearLayers();
                    const renderedSources = new Set();
                    const renderedDestinations = new Set();
                    this.arcs.filter(arc => this.isCountryEnabled(arc.source.country)).forEach(arc => {
                        const color = this.color(arc.severity);
                        const points = this.curve(arc.source, arc.destination);
                        const weight = Math.min(2.2, .8 + Math.log2(arc.count + 1) * .22);
                        L.polyline(points, { renderer, color: '#0f172a', weight: weight + 1.2, opacity: .45, interactive: false }).addTo(layer);
                        L.polyline(points, { renderer, color, weight, opacity: .9, interactive: false }).addTo(layer);
                        L.polyline(points, { color: '#ffffff', weight: .8, opacity: .85, dashArray: '2 12', className: 'attack-arc', interactive: false }).addTo(layer);
                        const hitLine = L.polyline(points, { color: '#000000', weight: 12, opacity: 0, interactive: true }).addTo(layer);
                        hitLine.bindPopup(`
                            <div style="min-width:240px;line-height:1.55">
                                <strong>Detail serangan</strong><br>
                                <b>Source:</b> ${this.escape(arc.source_ip)}:${this.escape(arc.source_port)}<br>
                                <b>Lokasi source:</b> ${this.escape(arc.source.city)} / ${this.escape(arc.source.country)}<br>
                                <b>Destination:</b> ${this.escape(arc.destination_ip)}:${this.escape(arc.destination_port)}<br>
                                <b>Lokasi destination:</b> ${this.escape(arc.destination.city)} / ${this.escape(arc.destination.country)}<br>
                                <b>Request:</b> ${this.escape(arc.method)} ${this.escape(arc.url)}<br>
                                <b>Service:</b> ${this.escape(arc.service)}<br>
                                <b>Attack:</b> ${this.escape(arc.attack)} (${this.escape(arc.severity)})<br>
                                <b>Action:</b> ${this.escape(arc.action)}<br>
                                <b>Event:</b> ${this.escape(arc.count)} &middot; <b>Terakhir:</b> ${this.escape(arc.last_event)}
                            </div>
                        `);
                        const sourceKey = `${arc.source_ip}|${arc.source.latitude},${arc.source.longitude}`;
                        if (!renderedSources.has(sourceKey)) {
                            renderedSources.add(sourceKey);
                            const sourceDiameter = 9 + Math.min(7, Math.log2(arc.count + 1));
                            const sourceMarker = L.marker([arc.source.latitude, arc.source.longitude], {
                                icon: this.locationIcon(`${arc.source.city || '-'} / ${arc.source.country}`, color, 'source', sourceDiameter, this.showSourceLabels),
                                keyboard: false,
                                zIndexOffset: 500,
                            }).addTo(layer);
                            sourceMarker.bindPopup(`<strong>${this.escape(arc.source_ip)}</strong><br>${this.escape(arc.source.city || '-')} / ${this.escape(arc.source.country)}<br>${this.escape(arc.count)} event`);
                        }
                        const destinationKey = `${arc.destination.latitude},${arc.destination.longitude}`;
                        if (!renderedDestinations.has(destinationKey)) {
                            renderedDestinations.add(destinationKey);
                            const destinationMarker = L.marker([arc.destination.latitude, arc.destination.longitude], {
                                icon: this.locationIcon(`${arc.destination.city || '-'} / ${arc.destination.country}`, '#06b6d4', 'destination', arc.destination.is_local ? 14 : 11, this.showDestinationLabels),
                                keyboard: false,
                                zIndexOffset: 600,
                            }).addTo(layer);
                            destinationMarker.bindPopup(`<strong>${this.escape(arc.destination_ip)}</strong><br>${this.escape(arc.destination.city || '-')} / ${this.escape(arc.destination.country)}`);
                        }
                    });
                },
                async load(showSpinner = true) {
                    if (!showSpinner && this.request) return;
                    this.request?.abort(); this.request = new AbortController();
                    const activeRequest = this.request;
                    if (showSpinner) this.loading = true;
                    try {
                        const url = new URL(this.endpoint, location.origin);
                        Object.entries(this.filters || {}).forEach(([key, value]) => { if (value) url.searchParams.set(key, value); });
                        url.searchParams.set('hours', this.hours);
                        if (this.severity) url.searchParams.set('severity', this.severity); else url.searchParams.delete('severity');
                        const response = await fetch(url, { signal: activeRequest.signal, headers: { Accept: 'application/json' } });
                        if (!response.ok) return;
                        const data = await response.json();
                        this.summary = data.summary;
                        this.geolocation = data.geolocation || { message: null, unresolved: 0, unresolved_ips: [] };
                        this.countries = data.countries;
                        const fingerprint = JSON.stringify(data.arcs);
                        const mapChanged = fingerprint !== this.lastFingerprint;
                        this.arcs = data.arcs;
                        const availableCountries = new Set(data.countries.map(country => country.country));
                        this.enabledCountries = Object.fromEntries(
                            data.countries.map(country => [country.country, this.enabledCountries[country.country] !== false])
                        );
                        Object.keys(this.enabledCountries).forEach(country => {
                            if (!availableCountries.has(country)) delete this.enabledCountries[country];
                        });
                        if (mapChanged) {
                            this.lastFingerprint = fingerprint;
                            this.draw();
                        }
                    } catch (_) {} finally {
                        if (this.request === activeRequest) this.request = null;
                        if (showSpinner) this.loading = false;
                    }
                }
            };
        }
    </script>
@endpush
