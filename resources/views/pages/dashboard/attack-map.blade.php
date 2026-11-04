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
                        <select x-model="hours" @change="load()" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700 dark:text-white">
                            <option value="1">1 jam</option><option value="6">6 jam</option><option value="12">12 jam</option>
                            <option value="24">24 jam</option><option value="72">3 hari</option><option value="168">7 hari</option>
                        </select>
                        <select x-model="severity" @change="load()" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Semua severity</option>
                            @foreach ($severities as $severity)<option value="{{ $severity }}">{{ $severity }}</option>@endforeach
                        </select>
                        <button type="button" @click="load()" class="h-9 rounded-lg bg-brand-500 px-4 text-xs font-medium text-white">Refresh</button>
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
    </div>
@endsection

@push('scripts')
    <style>
        #attack-world-map { background:#dbeafe; }
        #attack-world-map .attack-arc { animation:attack-dash 2.2s linear infinite; }
        #attack-world-map .attack-source-label {
            border:1px solid rgba(255,255,255,.65); border-radius:6px; background:rgba(15,23,42,.82);
            box-shadow:0 2px 8px rgba(15,23,42,.22); color:#fff; font-size:10px; font-weight:600;
            line-height:1.2; padding:3px 6px; white-space:nowrap;
        }
        #attack-world-map .attack-source-label::before { display:none; }
        #attack-world-map .attack-destination-label {
            border:1px solid rgba(255,255,255,.75); border-radius:6px; background:rgba(8,145,178,.88);
            box-shadow:0 2px 8px rgba(15,23,42,.22); color:#fff; font-size:10px; font-weight:700;
            line-height:1.2; padding:3px 6px; white-space:nowrap;
        }
        #attack-world-map .attack-destination-label::before { display:none; }
        #attack-world-map .leaflet-tooltip-pane,
        #attack-world-map .leaflet-tooltip { transition:none !important; }
        @keyframes attack-dash { to { stroke-dashoffset:-28; } }
    </style>
    <script>
        function attackMap(endpoint, initialFilters = {}) {
            return {
                endpoint, map: null, layer: null, canvasRenderer: null, timer: null, mapInitTimer: null, resizeTimers: [], request: null, visibilityHandler: null, destroyed: false, loading: false, mapError: '', lastFingerprint: null,
                filters: initialFilters, hours: initialFilters.hours || '24', severity: initialFilters.severity || '', countries: [], arcs: [], enabledCountries: {}, geolocation: { message: null, unresolved: 0, unresolved_ips: [] }, summary: { total: 0, sources: 0, countries: 0, blocked: 0 },
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
                    this.request?.abort(); this.map?.remove();
                },
                escape(value) {
                    return String(value ?? '-').replace(/[&<>'"]/g, character => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#039;', '"':'&quot;' }[character]));
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
                        const sourceMarker = L.circleMarker([arc.source.latitude, arc.source.longitude], { renderer, radius: 4 + Math.min(4, Math.log2(arc.count + 1) * .45), color: '#fff', fillColor: color, fillOpacity: 1, weight: 1.5 }).addTo(layer);
                        sourceMarker.bindTooltip(`${arc.source.city || '-'} / ${arc.source.country}`, { permanent: true, direction: 'top', offset: [0, -8], className: 'attack-source-label' });
                        sourceMarker.bindPopup(`<strong>${arc.source_ip}</strong><br>${arc.source.city || '-'}, ${arc.source.country}<br>${arc.count} event`);
                        const destinationKey = `${arc.destination.latitude},${arc.destination.longitude}`;
                        if (!renderedDestinations.has(destinationKey)) {
                            renderedDestinations.add(destinationKey);
                            const destinationMarker = L.circleMarker([arc.destination.latitude, arc.destination.longitude], { renderer, radius: arc.destination.is_local ? 7 : 5, color: '#fff', fillColor: '#06b6d4', fillOpacity: 1, weight: 2 }).addTo(layer);
                            destinationMarker.bindTooltip(`${arc.destination.city || '-'} / ${arc.destination.country}`, { permanent: true, direction: 'bottom', offset: [0, 8], className: 'attack-destination-label' });
                            destinationMarker.bindPopup(`<strong>${arc.destination_ip}</strong><br>${arc.destination.city || '-'}, ${arc.destination.country}`);
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
