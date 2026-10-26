@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Data Center Room" />

    <div
        class="flex min-h-0 flex-1 flex-col gap-4"
        x-data="dataCenterRoom(@js($devices), @js($storageKey))"
        x-init="init()"
    >
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">Simulasi Data Center Room</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Atur jumlah rak lalu tarik perangkat inventaris DC-DRC ke rak tujuan. Dalam simulasi ini satu perangkat dihitung 1U.</p>
                    <div class="mt-3 inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        <template x-for="room in ['DC', 'DRC']" :key="room">
                            <button type="button" @click="switchRoom(room)"
                                class="rounded-lg px-5 py-2 text-sm font-bold transition"
                                :class="currentRoom === room ? 'bg-sky-600 text-white shadow' : 'text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700'"
                                x-text="'Room ' + room"></button>
                        </template>
                    </div>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cari perangkat / VM</span>
                        <input type="search" x-model="search" placeholder="Nama, IP, tipe, VM..."
                            class="h-10 w-64 rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah rak</span>
                        <input type="number" min="1" max="20" x-model.number="rackCountDraft"
                            @keydown.enter.prevent="applyRackCount()"
                            class="h-10 w-24 rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                    </label>
                    <button type="button" @click="applyRackCount()"
                        class="h-10 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white hover:bg-sky-700">Terapkan</button>
                    <button type="button" @click="resetLayout()"
                        class="h-10 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Reset layout</button>
                </div>
            </div>
        </div>

        <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[300px_minmax(0,1fr)]">
            <aside class="flex min-h-[420px] flex-col rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-bold text-slate-900 dark:text-white" x-text="'Perangkat ' + currentRoom + ' tersedia'"></h2>
                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-bold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300" x-text="availableDevices.length"></span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Gunakan pencarian di atas untuk menemukan perangkat atau VM.</p>
                </div>
                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3"
                    @dragover.prevent @drop.prevent="dropToPool($event)">
                    <template x-for="device in filteredAvailableDevices" :key="device.id">
                        <article draggable="true" @dragstart="startDrag($event, device.id)" @dragend="finishDrag()"
                            class="cursor-grab rounded-xl border p-3 transition active:cursor-grabbing"
                            :class="matchesSearch(device) ? 'border-red-500 bg-red-50 ring-2 ring-red-400/70 dark:border-red-400 dark:bg-red-950/50' : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800'">
                            <div class="truncate text-sm font-bold text-slate-800 dark:text-white" x-text="device.server_name"></div>
                            <div class="mt-1 flex flex-wrap gap-1 text-[11px]">
                                <span class="rounded bg-slate-200 px-1.5 py-0.5 text-slate-600 dark:bg-slate-700 dark:text-slate-300" x-text="deviceTypeLabel(device)"></span>
                                <span class="rounded bg-violet-100 px-1.5 py-0.5 font-semibold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300" x-text="deviceRoom(device)"></span>
                                <span class="rounded bg-sky-100 px-1.5 py-0.5 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300" x-show="device.ip_address" x-text="device.ip_address"></span>
                            </div>
                        </article>
                    </template>
                    <div x-show="filteredAvailableDevices.length === 0" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">
                        Tidak ada perangkat tersedia.
                    </div>
                </div>
            </aside>

            <section class="min-h-[420px] overflow-auto rounded-2xl border border-slate-200 bg-slate-100/70 p-4 shadow-inner dark:border-slate-700 dark:bg-slate-950/50">
                <div class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-200">
                    <span class="h-2.5 w-2.5 rounded-full" :class="currentRoom === 'DC' ? 'bg-sky-500' : 'bg-violet-500'"></span>
                    <span x-text="'Room ' + currentRoom"></span>
                </div>
                <div class="flex min-w-max items-start gap-4">
                    <template x-for="rack in racks" :key="rack.id">
                        <div class="relative w-64 rounded-xl border-2 border-slate-700 bg-slate-900 shadow-xl hover:z-40"
                            @dragover.prevent @drop.prevent="dropToRack($event, rack.id)">
                            <div class="flex items-center justify-between rounded-t-lg border-b border-slate-700 bg-slate-800 px-3 py-2 text-white">
                                <input x-model="rack.name" @change="save()" aria-label="Nama rak"
                                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm font-bold text-white focus:ring-0">
                                <span class="ml-2 text-[10px] text-slate-400" x-text="rack.devices.length + '/42U'"></span>
                            </div>
                            <div class="relative min-h-[672px] rounded-b-lg bg-slate-950 p-2">
                                <div class="pointer-events-none absolute inset-2 grid grid-rows-[repeat(42,16px)]">
                                    <template x-for="unit in 42" :key="unit">
                                        <div class="border-b border-slate-800 text-[8px] leading-4 text-slate-600" x-text="43 - unit"></div>
                                    </template>
                                </div>
                                <div class="relative z-10 ml-5 space-y-1">
                                    <template x-for="deviceId in rack.devices" :key="deviceId">
                                        <article draggable="true" @dragstart="startDrag($event, deviceId)" @dragend="finishDrag()"
                                            @click="openDeviceDetail(deviceId)"
                                            class="group/device relative flex h-12 cursor-grab items-center gap-2 rounded border px-2 text-white shadow transition hover:z-50 active:cursor-grabbing"
                                            :class="matchesSearch(deviceById(deviceId)) ? 'border-red-300 bg-gradient-to-r from-red-700 to-rose-600 ring-2 ring-red-400 shadow-red-950/50' : 'border-sky-400/60 bg-gradient-to-r from-sky-700 to-cyan-700'">
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-300 shadow-[0_0_6px_#6ee7b7]"></span>
                                            <div class="min-w-0 flex-1">
                                                <div class="truncate text-xs font-bold" x-text="deviceById(deviceId)?.server_name || 'Perangkat'"></div>
                                                <div x-show="deviceMeta(deviceId)" class="truncate text-[9px] text-sky-100" x-text="deviceMeta(deviceId)"></div>
                                            </div>
                                            <button type="button" @click.stop="removeFromRack(deviceId)" title="Kembalikan ke daftar"
                                                class="hidden rounded px-1 text-sm hover:bg-white/20 group-hover/device:block">&times;</button>

                                            <div class="pointer-events-none invisible absolute left-[calc(100%+8px)] top-0 z-50 w-72 translate-x-1 rounded-xl border border-slate-600 bg-slate-900 p-3 opacity-0 shadow-2xl transition group-hover/device:visible group-hover/device:translate-x-0 group-hover/device:opacity-100">
                                                <div class="mb-2 flex items-center justify-between gap-2 border-b border-slate-700 pb-2">
                                                    <div class="min-w-0">
                                                        <div class="truncate text-xs font-bold text-white" x-text="deviceById(deviceId)?.server_name"></div>
                                                        <div class="text-[9px] text-slate-400" x-text="deviceTypeLabel(deviceById(deviceId))"></div>
                                                    </div>
                                                    <span x-show="isServer(deviceById(deviceId)) && (deviceById(deviceId)?.hosted_vms || []).length > 0"
                                                        class="rounded-full bg-violet-500/20 px-2 py-0.5 text-[10px] font-bold text-violet-200"
                                                        x-text="(deviceById(deviceId)?.hosted_vms || []).length + ' VM'"></span>
                                                </div>
                                                <dl class="grid grid-cols-[78px_minmax(0,1fr)] gap-x-2 gap-y-1 text-[10px]">
                                                    <template x-for="detail in deviceDetails(deviceById(deviceId))" :key="detail.label">
                                                        <div class="contents">
                                                            <dt class="text-slate-500" x-text="detail.label"></dt>
                                                            <dd class="truncate font-medium text-slate-200" x-text="detail.value"></dd>
                                                        </div>
                                                    </template>
                                                </dl>
                                                <div x-show="isServer(deviceById(deviceId)) && (deviceById(deviceId)?.hosted_vms || []).length > 0"
                                                    class="mt-3 border-t border-slate-700 pt-2">
                                                    <div class="mb-1.5 text-[10px] font-bold uppercase tracking-wide text-violet-300">Virtual Machine</div>
                                                    <div class="max-h-44 space-y-1.5 overflow-y-auto">
                                                        <template x-for="vm in (deviceById(deviceId)?.hosted_vms || [])" :key="vm.id">
                                                            <div class="rounded-lg px-2.5 py-2"
                                                                :class="vmMatchesSearch(vm) ? 'bg-red-900/80 ring-1 ring-red-400' : 'bg-slate-800'">
                                                                <div class="truncate text-[11px] font-semibold text-white" x-text="vm.server_name"></div>
                                                                <div class="mt-0.5 truncate text-[9px] text-slate-400"
                                                                    x-text="[vm.ip_address, vm.os, vm.status].filter(Boolean).join(' · ') || 'Detail tidak tersedia'"></div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    </template>
                                    <div x-show="rack.devices.length === 0" class="flex h-24 items-center justify-center rounded-lg border border-dashed border-slate-700 text-center text-xs text-slate-500">
                                        Tarik perangkat ke sini
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>
        </div>

        <div x-show="selectedDevice" x-cloak @keydown.escape.window="closeDeviceDetail()"
            class="fixed inset-0 z-[100010] flex items-center justify-center p-4 sm:p-6">
            <div class="absolute inset-0 bg-slate-950/75 backdrop-blur-sm" @click="closeDeviceDetail()"></div>
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-700 bg-white shadow-2xl dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">Detail perangkat</div>
                        <h2 class="truncate text-xl font-bold text-slate-900 dark:text-white" x-text="selectedDevice?.server_name"></h2>
                    </div>
                    <button type="button" @click="closeDeviceDetail()" aria-label="Tutup detail"
                        class="ml-4 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-300 text-xl text-slate-500 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">&times;</button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    <div class="grid gap-5"
                        :class="isServer(selectedDevice) ? 'lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.9fr)]' : 'grid-cols-1'">
                        <section>
                            <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">Informasi perangkat</h3>
                            <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <template x-for="detail in fullDeviceDetails(selectedDevice)" :key="detail.label">
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800/60">
                                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500" x-text="detail.label"></dt>
                                        <dd class="mt-1 break-words text-sm font-medium text-slate-800 dark:text-slate-100" x-text="detail.value || '-' "></dd>
                                    </div>
                                </template>
                            </dl>
                        </section>

                        <section x-show="isServer(selectedDevice)">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Virtual Machine</h3>
                                <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-bold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                                    x-text="(selectedDevice?.hosted_vms || []).length + ' VM'"></span>
                            </div>
                            <div class="space-y-2">
                                <template x-for="vm in (selectedDevice?.hosted_vms || [])" :key="vm.id">
                                    <article class="rounded-xl border p-3"
                                        :class="vmMatchesSearch(vm) ? 'border-red-500 bg-red-50 ring-2 ring-red-400 dark:border-red-400 dark:bg-red-950/40' : 'border-slate-200 dark:border-slate-700 dark:bg-slate-800/50'">
                                        <div class="font-bold text-slate-900 dark:text-white" x-text="vm.server_name"></div>
                                        <div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-300">
                                            <span>IP: <b x-text="vm.ip_address || '-'"></b></span>
                                            <span>VLAN: <b x-text="vm.vlan || '-'"></b></span>
                                            <span>OS: <b x-text="vm.os || '-'"></b></span>
                                            <span>Status: <b x-text="vm.status || '-'"></b></span>
                                            <span>CPU: <b x-text="vm.cpu_cores ? vm.cpu_cores + ' core' : '-'"></b></span>
                                            <span>RAM: <b x-text="vm.ram_gb ? vm.ram_gb + ' GB' : '-'"></b></span>
                                            <span class="col-span-2">Storage: <b x-text="vm.storage_gb ? vm.storage_gb + ' GB' : '-'"></b></span>
                                        </div>
                                    </article>
                                </template>
                                <div x-show="(selectedDevice?.hosted_vms || []).length === 0"
                                    class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">
                                    Server ini belum memiliki VM yang terhubung.
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function dataCenterRoom(rawDevices, storageKey) {
            return {
                devices: rawDevices,
                currentRoom: 'DC',
                rooms: { DC: { racks: [] }, DRC: { racks: [] } },
                rackCountDraft: 3,
                search: '',
                draggedId: null,
                isDragging: false,
                selectedDevice: null,
                storageKey,
                get racks() { return this.rooms[this.currentRoom].racks; },
                set racks(value) { this.rooms[this.currentRoom].racks = value; },
                init() {
                    let saved = null;
                    try { saved = JSON.parse(localStorage.getItem(this.storageKey)); } catch (_) { saved = null; }
                    const validIds = new Set(this.devices.map(device => Number(device.id)));
                    const sanitizeRacks = (source, room) => {
                        if (!Array.isArray(source) || source.length === 0) return this.makeRacks(3, room);
                        return source.slice(0, 20).map((rack, index) => ({
                            id: Number(rack.id) || index + 1,
                            name: String(rack.name || `${room} Rack ${index + 1}`),
                            devices: (Array.isArray(rack.devices) ? rack.devices : [])
                                .map(Number).filter(id => validIds.has(id)),
                        }));
                    };
                    if (saved?.rooms) {
                        this.rooms.DC.racks = sanitizeRacks(saved.rooms.DC?.racks, 'DC');
                        this.rooms.DRC.racks = sanitizeRacks(saved.rooms.DRC?.racks, 'DRC');
                    } else if (saved && Array.isArray(saved.racks) && saved.racks.length) {
                        this.rooms.DC.racks = sanitizeRacks(saved.racks, 'DC');
                        this.rooms.DRC.racks = this.makeRacks(3, 'DRC');
                    } else {
                        this.rooms.DC.racks = this.makeRacks(3, 'DC');
                        this.rooms.DRC.racks = this.makeRacks(3, 'DRC');
                    }
                    this.normalizeRoomAssignments();
                    this.rackCountDraft = this.racks.length;
                    this.save();
                },
                makeRacks(count, room = this.currentRoom) {
                    return Array.from({ length: count }, (_, index) => ({ id: index + 1, name: `${room} Rack ${index + 1}`, devices: [] }));
                },
                deviceRoom(device) {
                    const site = String(device?.site || '').trim().toLowerCase();
                    return site.includes('drc') || site.includes('disaster') || site.includes('recovery') ? 'DRC' : 'DC';
                },
                normalizeRoomAssignments() {
                    const used = new Set();
                    const misplaced = { DC: [], DRC: [] };
                    ['DC', 'DRC'].forEach(room => {
                        this.rooms[room].racks.forEach(rack => {
                            rack.devices = rack.devices.filter(id => {
                                const numericId = Number(id);
                                const device = this.deviceById(numericId);
                                if (!device || used.has(numericId)) return false;
                                used.add(numericId);
                                const targetRoom = this.deviceRoom(device);
                                if (targetRoom !== room) {
                                    misplaced[targetRoom].push(numericId);
                                    return false;
                                }
                                return true;
                            });
                        });
                    });
                    ['DC', 'DRC'].forEach(room => {
                        misplaced[room].forEach(id => {
                            const rack = this.rooms[room].racks.find(item => item.devices.length < 42);
                            if (rack) rack.devices.push(id);
                        });
                    });
                },
                switchRoom(room) {
                    if (!['DC', 'DRC'].includes(room)) return;
                    this.currentRoom = room;
                    this.rackCountDraft = this.racks.length;
                    this.search = '';
                },
                get placedIds() {
                    return new Set(['DC', 'DRC'].flatMap(room => this.rooms[room].racks.flatMap(rack => rack.devices)).map(Number));
                },
                get availableDevices() {
                    return this.devices.filter(device => this.deviceRoom(device) === this.currentRoom && !this.placedIds.has(Number(device.id)));
                },
                get filteredAvailableDevices() {
                    if (!this.search.trim()) return this.availableDevices;
                    return this.availableDevices.filter(device => this.matchesSearch(device));
                },
                deviceById(id) { return this.devices.find(device => Number(device.id) === Number(id)); },
                searchHaystack(values) {
                    return values.flat(Infinity).filter(value => value !== null && value !== undefined).join(' ').toLowerCase();
                },
                vmMatchesSearch(vm) {
                    const query = this.search.trim().toLowerCase();
                    if (!query || !vm) return false;
                    return this.searchHaystack([
                        vm.server_name, vm.ip_address, vm.vlan, vm.os, vm.status,
                        vm.system_role, vm.environment, vm.owner_team, vm.notes,
                    ]).includes(query);
                },
                matchesSearch(device) {
                    const query = this.search.trim().toLowerCase();
                    if (!query || !device) return false;
                    const deviceMatch = this.searchHaystack([
                        device.server_name, device.device_type, device.ip_address, device.vlan,
                        device.nic_model, device.os, device.site, device.status,
                        device.system_role, device.environment, device.owner_team, device.notes,
                    ]).includes(query);
                    return deviceMatch || (device.hosted_vms || []).some(vm => this.vmMatchesSearch(vm));
                },
                isServer(device) {
                    const type = String(device?.device_type || '').toLowerCase().replace(/[\s_-]/g, '');
                    return type === 'vmhost' || type === 'baremetal' || type === 'physical';
                },
                deviceTypeLabel(device) { return this.isServer(device) ? 'Server baremetal' : (device?.device_type || 'Tidak diketahui'); },
                openDeviceDetail(id) {
                    const device = this.deviceById(id);
                    if (!this.isDragging && device) this.selectedDevice = device;
                },
                closeDeviceDetail() { this.selectedDevice = null; },
                fullDeviceDetails(device) {
                    if (!device) return [];
                    return [
                        { label: 'Tipe perangkat', value: this.deviceTypeLabel(device) },
                        { label: 'IP Address', value: device.ip_address },
                        { label: 'VLAN', value: device.vlan },
                        { label: 'Lokasi', value: device.site },
                        { label: 'Status', value: device.status },
                        { label: 'Fungsi sistem', value: device.system_role },
                        { label: 'OS', value: device.os },
                        { label: 'NIC / Model', value: device.nic_model },
                        { label: 'CPU', value: device.cpu_cores ? `${device.cpu_cores} core` : '' },
                        { label: 'RAM', value: device.ram_gb ? `${device.ram_gb} GB` : '' },
                        { label: 'Storage', value: device.storage_gb ? `${device.storage_gb} GB` : '' },
                        { label: 'Environment', value: device.environment },
                        { label: 'Owner Team', value: device.owner_team },
                        { label: 'OBJID CPU', value: device.objid_cpu },
                        { label: 'OBJID RAM', value: device.objid_ram },
                        { label: 'OBJID Ping', value: device.objid_ping },
                        { label: 'OBJID Disk Free', value: device.objid_diskfree },
                        { label: 'OBJID Traffic', value: device.objid_traffic },
                        { label: 'Keterangan', value: device.notes },
                    ];
                },
                deviceDetails(device) {
                    if (!device) return [];
                    const capacity = [
                        device.cpu_cores ? `${device.cpu_cores} CPU` : '',
                        device.ram_gb ? `${device.ram_gb} GB RAM` : '',
                        device.storage_gb ? `${device.storage_gb} GB Storage` : '',
                    ].filter(Boolean).join(' · ');
                    return [
                        { label: 'IP Address', value: device.ip_address },
                        { label: 'VLAN', value: device.vlan },
                        { label: 'Lokasi', value: device.site },
                        { label: 'Status', value: device.status },
                        { label: 'Fungsi', value: device.system_role },
                        { label: 'OS / Model', value: device.os || device.nic_model },
                        { label: 'Kapasitas', value: capacity },
                        { label: 'Environment', value: device.environment },
                        { label: 'Owner', value: device.owner_team },
                    ].filter(detail => detail.value);
                },
                deviceMeta(id) {
                    const device = this.deviceById(id);
                    if (!device) return '';
                    if (!this.isServer(device)) return device.device_type || 'Perangkat DC/DRC';
                    const vmNames = (device.hosted_vms || []).map(vm => vm.server_name).filter(Boolean);
                    return vmNames.join(', ');
                },
                startDrag(event, id) {
                    this.isDragging = true;
                    this.draggedId = Number(id);
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(id));
                },
                finishDrag() {
                    this.draggedId = null;
                    window.setTimeout(() => { this.isDragging = false; }, 0);
                },
                draggedDeviceId(event) { return Number(event.dataTransfer.getData('text/plain') || this.draggedId); },
                dropToRack(event, rackId) {
                    const id = this.draggedDeviceId(event);
                    if (!this.deviceById(id)) return;
                    const target = this.racks.find(rack => rack.id === rackId);
                    if (!target || (target.devices.length >= 42 && !target.devices.some(deviceId => Number(deviceId) === id))) return;
                    this.racks.forEach(rack => { rack.devices = rack.devices.filter(deviceId => Number(deviceId) !== id); });
                    target.devices.push(id);
                    this.draggedId = null;
                    this.save();
                },
                dropToPool(event) { this.removeFromRack(this.draggedDeviceId(event)); },
                removeFromRack(id) {
                    this.racks.forEach(rack => { rack.devices = rack.devices.filter(deviceId => Number(deviceId) !== Number(id)); });
                    this.draggedId = null;
                    this.save();
                },
                applyRackCount() {
                    const count = Math.max(1, Math.min(20, Number(this.rackCountDraft) || 1));
                    if (count < this.racks.length) {
                        const removedDevices = this.racks.slice(count).flatMap(rack => rack.devices);
                        this.racks = this.racks.slice(0, count);
                        removedDevices.forEach(id => {
                            const target = this.racks.find(rack => rack.devices.length < 42);
                            if (target) target.devices.push(id);
                        });
                    } else {
                        const maxId = Math.max(0, ...this.racks.map(rack => rack.id));
                        for (let index = this.racks.length; index < count; index++) {
                            this.racks.push({ id: maxId + index - this.racks.length + 1, name: `${this.currentRoom} Rack ${index + 1}`, devices: [] });
                        }
                    }
                    this.rackCountDraft = count;
                    this.save();
                },
                resetLayout() {
                    if (!window.confirm(`Kosongkan seluruh penempatan perangkat Room ${this.currentRoom} dan kembalikan menjadi 3 rak?`)) return;
                    this.racks = this.makeRacks(3, this.currentRoom);
                    this.rackCountDraft = 3;
                    this.save();
                },
                save() {
                    try { localStorage.setItem(this.storageKey, JSON.stringify({ rooms: this.rooms })); } catch (_) { /* storage unavailable */ }
                },
            };
        }
    </script>
@endsection
