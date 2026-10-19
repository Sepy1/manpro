@extends('layouts.admin')

@section('admin-content')
    <div class="network-map-page flex h-full min-h-0 flex-1 flex-col gap-2 overflow-hidden sm:gap-4" x-data="networkMap(@js($locations), @js(route('admin.map.status')), @js($highPingThresholdMs))" x-init="init()">
        <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-end sm:justify-between sm:gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-500 sm:text-xs sm:tracking-[0.2em]">Monitoring jaringan</p>
                <h1 class="mt-0.5 text-xl font-bold text-gray-900 dark:text-white sm:mt-1 sm:text-2xl">Peta Kantor Jawa Tengah</h1>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 sm:mt-1 sm:text-sm">Sebaran kantor cabang dan kantor kas yang telah memiliki koordinat.</p>
            </div>
            @if (auth()->user()?->role === 'admin')
                <a href="{{ route('admin.manajemen-kantor.index') }}" class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-brand-500 px-3 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-500/10 sm:h-10 sm:w-auto sm:px-4 sm:text-sm">
                    Kelola koordinat
                </a>
            @endif
        </div>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex shrink-0 flex-col gap-2 border-b border-gray-200 p-2 sm:flex-row sm:items-center sm:gap-3 sm:p-3 dark:border-gray-800">
                <div class="relative min-w-0 flex-1">
                    <input x-model="search" @input.debounce.200ms="refreshMarkers()" type="search" placeholder="Cari nama atau kode kantor..." class="h-9 w-full rounded-lg border border-gray-300 bg-transparent pl-3 pr-9 text-xs dark:border-gray-700 dark:text-white sm:h-10 sm:text-sm" />
                </div>
                <div class="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    <span class="grid w-full grid-cols-2 gap-x-3 gap-y-1 text-[9px] text-gray-500 sm:hidden dark:text-gray-400">
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-blue-600"></i> Reply</span>
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-red-600"></i> RTO</span>
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-amber-400"></i> High ping / Kas bermasalah</span>
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-slate-500"></i> Belum diuji</span>
                    </span>
                    <span class="hidden items-center gap-3 pr-2 text-[11px] text-gray-500 lg:flex dark:text-gray-400">
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-blue-600"></i> Reply</span>
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-red-600"></i> RTO</span>
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-amber-400"></i> High ping / Kas bermasalah</span>
                        <span class="inline-flex items-center gap-1"><i class="h-2.5 w-2.5 rounded-full bg-slate-500"></i> Belum diuji</span>
                    </span>
                    <div class="grid w-full grid-cols-3 gap-1.5 sm:flex sm:w-auto sm:gap-2">
                        <button type="button" @click="toggleType('cabang')" :class="showCabang ? 'bg-brand-600 text-white border-brand-600' : 'border-gray-300 text-gray-500 dark:border-gray-700'" class="h-8 min-w-0 rounded-lg border px-1 text-[10px] font-semibold sm:h-9 sm:px-3 sm:text-xs">Cabang</button>
                        <button type="button" @click="toggleType('kas')" :class="showKas ? 'bg-brand-600 text-white border-brand-600' : 'border-gray-300 text-gray-500 dark:border-gray-700'" class="h-8 min-w-0 rounded-lg border px-1 text-[10px] font-semibold sm:h-9 sm:px-3 sm:text-xs">Kantor Kas</button>
                        <button type="button" @click="fitVisible()" class="h-8 min-w-0 rounded-lg border border-gray-300 px-1 text-[10px] font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300 sm:h-9 sm:px-3 sm:text-xs">Lihat semua</button>
                    </div>
                </div>
            </div>
            <div class="relative min-h-[280px] flex-1 overflow-hidden bg-slate-100 dark:bg-slate-950 sm:min-h-[420px]">
                <div id="network-map" class="absolute inset-0 z-0 h-full w-full"></div>
                <div x-show="locations.length === 0" class="pointer-events-none absolute inset-0 z-[500] flex items-center justify-center bg-white/75 p-6 text-center backdrop-blur-sm dark:bg-gray-900/75">
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-white">Belum ada koordinat kantor</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Isi latitude dan longitude pada halaman Manajemen Kantor.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIINfQ3ynhALrJPQWgBLRDlcxIVwyPQZBkg=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        /* Leaflet core layout fallback. Tetap lokal agar tile tidak berantakan saat CSS CDN diblokir/lambat. */
        .leaflet-container { position:relative; overflow:hidden; outline:0; background:#dbeafe; font-family:inherit; }
        .leaflet-container.leaflet-touch-zoom { touch-action:pan-x pan-y; }
        .leaflet-container.leaflet-touch-drag { touch-action:none; touch-action:pinch-zoom; }
        .leaflet-container img.leaflet-tile,
        .leaflet-container .leaflet-marker-icon,
        .leaflet-container .leaflet-marker-shadow { max-width:none !important; max-height:none !important; width:auto; padding:0; }
        .leaflet-pane,
        .leaflet-tile,
        .leaflet-marker-icon,
        .leaflet-marker-shadow,
        .leaflet-tile-container,
        .leaflet-pane > svg,
        .leaflet-pane > canvas,
        .leaflet-zoom-box { position:absolute; left:0; top:0; }
        .leaflet-pane { z-index:400; }
        .leaflet-tile-pane { z-index:200; }
        .leaflet-overlay-pane { z-index:400; }
        .leaflet-shadow-pane { z-index:500; }
        .leaflet-marker-pane { z-index:600; }
        .leaflet-tooltip-pane { z-index:650; }
        .leaflet-popup-pane { z-index:700; }
        .leaflet-map-pane canvas { z-index:100; }
        .leaflet-map-pane svg { z-index:200; }
        .leaflet-tile { visibility:hidden; filter:inherit; }
        .leaflet-tile-loaded { visibility:inherit; }
        .leaflet-zoom-animated { transform-origin:0 0; }
        .leaflet-interactive { cursor:pointer; }
        .leaflet-grab { cursor:grab; }
        .leaflet-dragging .leaflet-grab { cursor:grabbing; }
        .leaflet-control { position:relative; z-index:800; pointer-events:auto; }
        .leaflet-top,
        .leaflet-bottom { position:absolute; z-index:1000; pointer-events:none; }
        .leaflet-top { top:0; }
        .leaflet-right { right:0; }
        .leaflet-bottom { bottom:0; }
        .leaflet-left { left:0; }
        .leaflet-control { float:left; clear:both; }
        .leaflet-right .leaflet-control { float:right; }
        .leaflet-top .leaflet-control { margin-top:10px; }
        .leaflet-bottom .leaflet-control { margin-bottom:10px; }
        .leaflet-left .leaflet-control { margin-left:10px; }
        .leaflet-right .leaflet-control { margin-right:10px; }
        .leaflet-control-zoom { border:1px solid #cbd5e1; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgb(15 23 42 / .14); }
        .leaflet-control-zoom a { display:block; width:32px; height:32px; line-height:30px; background:#fff; color:#334155; text-align:center; text-decoration:none; font-size:20px; }
        .leaflet-control-zoom a + a { border-top:1px solid #e2e8f0; }
        .leaflet-control-attribution { margin:0; padding:2px 6px; background:rgb(255 255 255 / .82); color:#475569; font-size:10px; }
        .leaflet-control-attribution a { color:#2563eb; }
        .leaflet-popup { position:absolute; margin-bottom:20px; text-align:center; }
        .leaflet-popup-content-wrapper { position:relative; overflow:hidden; padding:1px; background:#fff; color:#1e293b; border-radius:12px; box-shadow:0 4px 20px rgb(15 23 42 / .2); text-align:left; }
        .leaflet-popup-content { overflow:hidden; margin:14px 18px; line-height:1.4; }
        .leaflet-popup-scrolled { overflow-x:hidden !important; overflow-y:auto !important; }
        .leaflet-popup-tip-container { position:absolute; left:50%; width:40px; height:20px; margin-left:-20px; overflow:hidden; pointer-events:none; }
        .leaflet-popup-tip { width:17px; height:17px; margin:-10px auto 0; padding:1px; background:#fff; transform:rotate(45deg); box-shadow:3px 3px 8px rgb(15 23 42 / .12); }
        .leaflet-popup-close-button { position:absolute; top:4px; right:6px; z-index:1; padding:4px; color:#64748b; text-decoration:none; font-size:20px; }
        .network-warning-popup { display:flex; width:280px; max-width:calc(100vw - 100px); max-height:min(420px, 62vh); flex-direction:column; overflow:hidden; }
        .network-warning-popup__summary { flex:0 0 auto; }
        .network-warning-popup__list { min-height:0; flex:1 1 auto; overflow-x:hidden; overflow-y:auto; overscroll-behavior:contain; padding-right:5px; }
        .office-map-marker { background: transparent; border: 0; }
        .office-pin { display:flex; align-items:center; justify-content:center; width:34px; height:34px; border:3px solid white; border-radius:50% 50% 50% 8px; color:white; box-shadow:0 4px 12px rgb(15 23 42 / .35); transform:rotate(-45deg); }
        .office-pin svg { transform:rotate(45deg); }
        .office-pin--reply { background:#2563eb; }
        .office-pin--rto { background:#dc2626; }
        .office-pin--warning { background:#f59e0b; }
        .office-pin--unknown { background:#64748b; }
        .office-pin--cash { width:29px; height:29px; }
        .leaflet-popup-content-wrapper { border-radius:12px; }
        @media (max-width: 639px) {
            .network-map-page { height:calc(100dvh - 5rem); }
            .leaflet-control-zoom a { width:29px; height:29px; line-height:27px; font-size:18px; }
            .leaflet-control-attribution { max-width:72vw; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; font-size:8px; }
            .leaflet-popup-content { margin:11px 12px; }
            .network-warning-popup { width:min(270px, calc(100vw - 105px)); max-height:52dvh; }
            .office-pin { width:31px; height:31px; }
            .office-pin--cash { width:27px; height:27px; }
        }
    </style>
    <script>
        function networkMap(locations, statusEndpoint, highPingThreshold) {
            return {
                locations,
                statusEndpoint,
                highPingThreshold,
                map: null,
                layer: null,
                provinceBounds: null,
                resizeObserver: null,
                search: '',
                showCabang: true,
                showKas: true,
                init() {
                    if (typeof L === 'undefined') return;
                    const jawaTengah = [
                        [-6.74608,108.829338],[-6.799195,108.854889],[-6.83733,108.952652],[-6.774646,109.027512],
                        [-6.849453,109.116945],[-6.873732,109.284729],[-6.783483,109.525772],[-6.837207,109.574196],
                        [-6.91085,109.83173],[-6.926026,109.994609],[-6.886767,110.135114],[-6.858777,110.218449],
                        [-6.910134,110.282545],[-6.958464,110.360636],[-6.946518,110.439422],[-6.918728,110.482428],
                        [-6.805168,110.567362],[-6.680221,110.63764],[-6.53139,110.692026],[-6.449049,110.72816],
                        [-6.409402,110.8402],[-6.406552,110.920682],[-6.421138,111.03451],[-6.595203,111.089495],
                        [-6.652081,111.135153],[-6.682676,111.288116],[-6.700903,111.353966],[-6.661293,111.460175],
                        [-6.616364,111.493095],[-6.638492,111.563789],[-6.752857,111.694595],[-6.836943,111.615326],
                        [-6.903624,111.609039],[-6.971057,111.612236],[-7.065532,111.62486],[-7.201196,111.580716],
                        [-7.280662,111.448722],[-7.353508,111.438232],[-7.352231,111.346387],[-7.295056,111.219641],
                        [-7.24923,111.241771],[-7.262771,111.146592],[-7.386832,111.132803],[-7.563807,111.151654],
                        [-7.709611,111.180164],[-7.747669,111.230736],[-7.782755,111.281219],[-7.85079,111.314003],
                        [-7.930492,111.269707],[-7.948041,111.231346],[-7.916514,111.181503],[-8.020032,111.134033],
                        [-8.065952,111.108658],[-8.082599,111.031685],[-8.063713,110.956131],[-8.121149,110.911171],
                        [-8.210409,110.904457],[-8.202934,110.836136],[-8.154592,110.788666],[-8.030742,110.758797],
                        [-7.899249,110.771225],[-7.818944,110.786538],[-7.82466,110.750929],[-7.791503,110.723794],
                        [-7.785867,110.657083],[-7.804361,110.588886],[-7.798195,110.54336],[-7.745311,110.498291],
                        [-7.536631,110.444958],[-7.595244,110.379147],[-7.683113,110.254532],[-7.645667,110.25954],
                        [-7.645877,110.142883],[-7.741732,110.132286],[-7.813906,110.06321],[-7.89204,110.042953],
                        [-7.886903,110.011826],[-7.844425,109.896675],[-7.792683,109.690193],[-7.756908,109.47419],
                        [-7.76845,109.424683],[-7.724499,109.380403],[-7.701472,109.277812],[-7.685618,109.105467],
                        [-7.697629,109.048995],[-7.744561,108.993577],[-7.720566,108.933997],[-7.70961,108.896909],
                        [-7.694295,108.876058],[-7.611036,108.865476],[-7.599123,108.827862],[-7.673145,108.804252],
                        [-7.609161,108.738433],[-7.543514,108.753132],[-7.486524,108.724345],[-7.421927,108.714968],
                        [-7.340506,108.662295],[-7.350607,108.603483],[-7.290434,108.555715],[-7.240565,108.582017],
                        [-7.171165,108.556373],[-7.137631,108.628929],[-7.151166,108.696854],[-7.116722,108.726479],
                        [-7.004968,108.798622],[-6.98307,108.761101],[-6.896383,108.76516],[-6.829293,108.824615],
                        [-6.74608,108.829338]
                    ];
                    const provinceBounds = L.latLngBounds(jawaTengah);
                    this.provinceBounds = provinceBounds;
                    this.map = L.map('network-map', {
                        zoomControl: true,
                        minZoom: 8,
                        zoomSnap: 0.25,
                        zoomDelta: 0.25,
                        maxBounds: provinceBounds.pad(0.35),
                        maxBoundsViscosity: 1
                    });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(this.map);

                    const world = [[-90,-180],[-90,180],[90,180],[90,-180]];
                    L.polygon([world, jawaTengah], {
                        stroke: false,
                        fillColor: '#0f172a',
                        fillOpacity: 0.97,
                        fillRule: 'evenodd',
                        interactive: false
                    }).addTo(this.map);
                    L.polygon(jawaTengah, {
                        color: '#38bdf8',
                        weight: 2.5,
                        opacity: 0.95,
                        fill: false,
                        interactive: false
                    }).addTo(this.map);

                    this.layer = L.featureGroup().addTo(this.map);
                    this.refreshMarkers();
                    this.focusProvince();
                    setTimeout(() => {
                        this.map.invalidateSize();
                        this.focusProvince();
                    }, 200);
                    this.resizeObserver = new ResizeObserver(() => {
                        window.clearTimeout(this.resizeTimer);
                        this.resizeTimer = window.setTimeout(() => {
                            this.map.invalidateSize({ animate: false });
                            this.focusProvince(false);
                        }, 120);
                    });
                    this.resizeObserver.observe(document.getElementById('network-map'));
                    this.statusTimer = window.setInterval(() => this.reloadStatuses(), 30000);
                },
                focusProvince(animate = false) {
                    if (!this.map || !this.provinceBounds) return;
                    this.map.fitBounds(this.provinceBounds, {
                        padding: [0, 0],
                        animate
                    });
                    /* Sedikit diperbesar agar bentuk Jawa Tengah memenuhi kanvas tanpa ruang tepi berlebih. */
                    this.map.setZoom(Math.min(this.map.getZoom() + 0.25, 10), { animate });
                },
                filtered() {
                    const needle = this.search.trim().toLowerCase();
                    return this.locations.filter(item => {
                        if ((item.type === 'cabang' && !this.showCabang) || (item.type === 'kas' && !this.showKas)) return false;
                        return !needle || `${item.code} ${item.name} ${item.parent || ''}`.toLowerCase().includes(needle);
                    });
                },
                icon(type, status) {
                    const branch = type === 'cabang';
                    const statusClass = status === 'reply' ? 'office-pin--reply' : (status === 'rto' ? 'office-pin--rto' : (status === 'warning' ? 'office-pin--warning' : 'office-pin--unknown'));
                    const svg = branch
                        ? '<svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M4 21h16M6 21V5h9v16M15 9h3v12M9 9h3M9 13h3M9 17h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
                        : '<svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-7h6v7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
                    return L.divIcon({ className: 'office-map-marker', html: `<span class="office-pin ${statusClass} ${branch ? '' : 'office-pin--cash'}">${svg}</span>`, iconSize: branch ? [34, 34] : [29, 29], iconAnchor: branch ? [17, 32] : [14, 27], popupAnchor: [0, -30] });
                },
                escape(value) {
                    const el = document.createElement('div');
                    el.textContent = value ?? '';
                    return el.innerHTML;
                },
                refreshMarkers() {
                    if (!this.layer) return;
                    this.layer.clearLayers();
                    const visible = this.filtered();
                    visible.forEach(item => {
                        const typeLabel = item.type === 'cabang' ? 'Kantor Cabang' : 'Kantor Kas';
                        const parent = item.parent ? `<p style="margin:4px 0 0;color:#64748b">Cabang: ${this.escape(item.parent)}</p>` : '';
                        const problemKas = Array.isArray(item.problem_kas) ? item.problem_kas : [];
                        const highPingKas = Array.isArray(item.high_ping_kas) ? item.high_ping_kas : [];
                        const isHighPing = item.ping_status === 'reply' && Number(item.ping_latency_ms) > Number(this.highPingThreshold);
                        const hasKasProblem = item.type === 'cabang' && item.ping_status !== 'rto' && (problemKas.length > 0 || highPingKas.length > 0);
                        const displayStatus = item.ping_status === 'rto' ? 'rto' : ((isHighPing || hasKasProblem) ? 'warning' : item.ping_status);
                        const pingLabel = isHighPing ? `High Ping · ${item.ping_latency_ms} ms` : (displayStatus === 'reply' ? 'Reply' : (displayStatus === 'rto' ? 'RTO' : (displayStatus === 'warning' ? `${problemKas.length + highPingKas.length} kantor kas bermasalah` : 'Belum diuji')));
                        const pingColor = displayStatus === 'reply' ? '#2563eb' : (displayStatus === 'rto' ? '#dc2626' : (displayStatus === 'warning' ? '#d97706' : '#64748b'));
                        const checkedAt = item.ping_checked_at ? new Date(item.ping_checked_at).toLocaleString('id-ID') : '-';
                        const problemList = problemKas.length
                            ? `<section><div style="position:sticky;top:0;z-index:1;padding:3px 0 5px;background:#fff"><strong style="font-size:11px;color:#dc2626">Kantor kas RTO (${problemKas.length})</strong></div>${problemKas.map(kas => `<div style="margin-bottom:6px;padding:6px 8px;border:1px solid #fecaca;border-radius:7px;background:#fef2f2;font-size:11px;color:#991b1b"><strong>${this.escape(kas.code)} · ${this.escape(kas.name)}</strong><br>Dicek: ${kas.ping_checked_at ? new Date(kas.ping_checked_at).toLocaleString('id-ID') : '-'}</div>`).join('')}</section>`
                            : '';
                        const highPingKasList = highPingKas.length
                            ? `<section style="margin-top:${problemKas.length ? '9px' : '0'}"><div style="position:sticky;top:0;z-index:1;padding:3px 0 5px;background:#fff"><strong style="font-size:11px;color:#b45309">Kantor kas high ping (${highPingKas.length})</strong></div>${highPingKas.map(kas => `<div style="margin-bottom:6px;padding:6px 8px;border-radius:7px;background:#fffbeb;font-size:11px;color:#78350f"><strong>${this.escape(kas.code)} · ${this.escape(kas.name)}</strong><br>Latency: <strong>${kas.ping_latency_ms} ms</strong> · Ambang: ${this.highPingThreshold} ms<br>Dicek: ${kas.ping_checked_at ? new Date(kas.ping_checked_at).toLocaleString('id-ID') : '-'}</div>`).join('')}</section>`
                            : '';
                        const highPingDetail = isHighPing
                            ? `<div style="margin-bottom:${problemKas.length || highPingKas.length ? '9px' : '0'};padding:8px 10px;border-radius:7px;background:#fffbeb;color:#92400e;font-size:11px"><strong>High Ping · ${typeLabel}</strong><div style="margin-top:4px;font-size:12px;color:#78350f"><strong>${this.escape(item.code)} · ${this.escape(item.name)}</strong></div>${item.parent ? `<div style="margin-top:2px">Cabang induk: ${this.escape(item.parent)}</div>` : ''}<div style="margin-top:6px">Latency: <strong>${item.ping_latency_ms} ms</strong><br>Ambang: ${this.highPingThreshold} ms<br>Diperiksa: ${checkedAt}</div></div>`
                            : '';
                        const popupContent = displayStatus === 'warning'
                            ? `<div class="network-warning-popup"><div class="network-warning-popup__summary">${highPingDetail}</div><div class="network-warning-popup__list">${problemList}${highPingKasList}</div></div>`
                            : `<div style="min-width:220px;max-width:290px"><strong>${this.escape(item.name)}</strong><p style="margin:4px 0 0;color:#64748b">${typeLabel} · ${this.escape(item.code)}</p>${parent}<p style="margin:8px 0 0"><strong style="color:${pingColor}">${pingLabel}</strong>${item.ping_latency_ms ? ` · ${item.ping_latency_ms} ms` : ''}</p><p style="margin:3px 0 0;font-size:11px;color:#94a3b8">Diperiksa: ${checkedAt}</p></div>`;
                        L.marker([item.latitude, item.longitude], { icon: this.icon(item.type, displayStatus), title: item.name })
                            .bindPopup(popupContent, {
                                autoPan: true,
                                keepInView: true,
                                autoPanPaddingTopLeft: [24, 110],
                                autoPanPaddingBottomRight: [24, 36],
                                maxHeight: 310
                            })
                            .addTo(this.layer);
                    });
                },
                async reloadStatuses() {
                    try {
                        const response = await fetch(this.statusEndpoint, { headers: { Accept: 'application/json' } });
                        if (!response.ok) return;
                        const data = await response.json();
                        if (Array.isArray(data.locations)) {
                            this.locations = data.locations;
                            if (Number(data.high_ping_threshold_ms) > 0) this.highPingThreshold = Number(data.high_ping_threshold_ms);
                            if (data.summary) this.updateSidebarSummary(data.summary);
                            this.refreshMarkers();
                        }
                    } catch (_) {
                        /* Pertahankan status terakhir jika jaringan browser sedang bermasalah. */
                    }
                },
                updateSidebarSummary(summary) {
                    window.dispatchEvent(new CustomEvent('network-summary-updated', { detail: summary }));
                    const values = {
                        'network-summary-kas-rto': summary.kas_rto,
                        'network-summary-cabang-rto': summary.cabang_rto,
                        'network-summary-slow': summary.slow,
                        'network-summary-threshold': summary.high_ping_threshold_ms
                    };
                    Object.entries(values).forEach(([id, value]) => {
                        const element = document.getElementById(id);
                        if (element) element.textContent = Number(value) || 0;
                    });
                },
                toggleType(type) {
                    if (type === 'cabang') this.showCabang = !this.showCabang;
                    if (type === 'kas') this.showKas = !this.showKas;
                    this.refreshMarkers();
                },
                fitVisible() {
                    this.refreshMarkers();
                    if (this.layer.getLayers().length) this.map.fitBounds(this.layer.getBounds().pad(0.15), { maxZoom: 12 });
                    else this.focusProvince();
                }
            };
        }
    </script>
@endpush
