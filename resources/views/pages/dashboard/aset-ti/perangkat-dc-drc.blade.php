@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Aset TI - Perangkat DC/DRC" />
    @php
        $nextSortDir = function (string $field) use ($sortBy, $sortDir): string {
            return $sortBy === $field && $sortDir === 'asc' ? 'desc' : 'asc';
        };
        $sortUrl = function (string $field) use ($nextSortDir): string {
            return route('admin.aset-ti.perangkat-dc-drc.index', array_merge(request()->except('sort_by', 'sort_dir'), [
                'sort_by' => $field,
                'sort_dir' => $nextSortDir($field),
            ]));
        };
        $sortIndicator = function (string $field) use ($sortBy, $sortDir): string {
            if ($sortBy !== $field) {
                return '↕';
            }

            return $sortDir === 'asc' ? '↑' : '↓';
        };
    @endphp

    <div class="flex min-h-0 h-full flex-col content-card p-5 lg:p-6"
        x-data="{
            showAddForm: @js($errors->any() && old('server_name')),
            showImportModal: @js($errors->has('import_file')),
            showEditModal: false,
            openHostId: null,
            updateActionBase: @js(route('admin.aset-ti.perangkat-dc-drc.update', ['device' => '__ID__'])),
            editForm: {
                id: '',
                server_name: '',
                device_type: '',
                host_server: '',
                vm_host_id: '',
                ip_address: '',
                vlan: '',
                nic_model: '',
                os: '',
                cpu_cores: '',
                ram_gb: '',
                storage_gb: '',
                objid_cpu: '',
                objid_ram: '',
                objid_ping: '',
                objid_diskfree: '',
                objid_traffic: '',
                site: '',
                system_role: '',
                environment: '',
                owner_team: '',
                status: '',
                notes: '',
            },
            openEditModal(el) {
                this.editForm = {
                    id: el.dataset.id || '',
                    server_name: el.dataset.serverName || '',
                    device_type: this.normalizeDeviceType(el.dataset.deviceType || ''),
                    host_server: el.dataset.hostServer || '',
                    vm_host_id: el.dataset.vmHostId || '',
                    ip_address: el.dataset.ipAddress || '',
                    vlan: el.dataset.vlan || '',
                    nic_model: el.dataset.nicModel || '',
                    os: el.dataset.os || '',
                    cpu_cores: el.dataset.cpuCores || '',
                    ram_gb: el.dataset.ramGb || '',
                    storage_gb: el.dataset.storageGb || '',
                    objid_cpu: el.dataset.objidCpu || '',
                    objid_ram: el.dataset.objidRam || '',
                    objid_ping: el.dataset.objidPing || '',
                    objid_diskfree: el.dataset.objidDiskfree || '',
                    objid_traffic: el.dataset.objidTraffic || '',
                    site: el.dataset.site || '',
                    system_role: el.dataset.systemRole || '',
                    environment: el.dataset.environment || '',
                    owner_team: el.dataset.ownerTeam || '',
                    status: el.dataset.status || '',
                    notes: el.dataset.notes || '',
                };
                this.showEditModal = true;
            },
            normalizeDeviceType(value) {
                const normalized = String(value || '').trim();
                if (normalized === '') {
                    return '';
                }

                const compact = normalized.toLowerCase().replace(/[\s_-]+/g, '');
                if (compact === 'vm') {
                    return 'VM';
                }
                if (compact === 'vmhost') {
                    return 'vm host';
                }
                if (compact === 'baremetal') {
                    return 'bare metal';
                }
                if (compact === 'physical') {
                    return 'physical';
                }

                return normalized;
            },
            get editAction() {
                return this.updateActionBase.replace('__ID__', this.editForm.id || '');
            },
        }">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="mb-4 space-y-3">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Manajemen Perangkat Server DC - DRC</h3>
            <form method="GET" action="{{ route('admin.aset-ti.perangkat-dc-drc.index') }}"
                class="flex items-center gap-2 overflow-x-auto pb-1">
                <input type="text" name="keyword" value="{{ $filters['keyword'] }}"
                    placeholder="Cari server, IP, OS, role, owner, status, dll"
                    class="h-10 w-[320px] shrink-0 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                <button type="submit"
                    class="inline-flex h-10 shrink-0 items-center rounded-lg border border-brand-500 px-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                    Filter
                </button>
                <a href="{{ route('admin.aset-ti.perangkat-dc-drc.index') }}"
                    class="inline-flex h-10 shrink-0 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/10">
                    Reset
                </a>
                <button type="button" @click="showImportModal = true"
                    class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-amber-500 px-4 text-sm font-medium text-amber-600 hover:bg-amber-50 dark:border-amber-400 dark:text-amber-300 dark:hover:bg-amber-900/20">
                    Import Excel
                </button>
                <a href="{{ route('admin.aset-ti.perangkat-dc-drc.export') }}" data-no-transition
                    class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-blue-500 px-4 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:border-blue-400 dark:text-blue-300 dark:hover:bg-blue-900/20">
                    Export Excel
                </a>
                <button type="button" @click="showAddForm = true"
                    class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                    Add Perangkat
                </button>
            </form>
        </div>

        <div class="min-h-0 flex-1 overflow-auto">
            <table class="w-full table-fixed border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="w-[10%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <a href="{{ $sortUrl('server_name') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Server <span>{{ $sortIndicator('server_name') }}</span></a>
                        </th>
                        <th class="w-[8%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <a href="{{ $sortUrl('device_type') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Tipe <span>{{ $sortIndicator('device_type') }}</span></a>
                        </th>
                        <th class="w-[9%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <a href="{{ $sortUrl('host_server') }}" class="inline-flex items-center gap-1 hover:text-brand-500">VM Server <span>{{ $sortIndicator('host_server') }}</span></a>
                        </th>
                        <th class="w-[10%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <a href="{{ $sortUrl('ip_address') }}" class="inline-flex items-center gap-1 hover:text-brand-500">IP Address <span>{{ $sortIndicator('ip_address') }}</span></a>
                        </th>
                        <th class="w-[6%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">VLAN</th>
                        <th class="w-[10%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">OS</th>
                        <th class="w-[6%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">CPU</th>
                        <th class="w-[6%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">RAM</th>
                        <th class="w-[7%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Storage</th>
                        <th class="w-[6%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Lokasi</th>
                        <th class="w-[8%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                        <th class="w-[14%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $isVmHost = fn ($deviceType) => strtolower(trim((string) $deviceType)) === 'vm host';
                        $isVm = fn ($deviceType) => strtolower(trim((string) $deviceType)) === 'vm';

                        $vmHosts = $devices->filter(fn ($device) => $isVmHost($device->device_type))->values();
                        $rootDevices = $devices->filter(function ($device) use ($isVmHost, $isVm) {
                            if ($isVmHost($device->device_type)) {
                                return false;
                            }

                            return !$isVm($device->device_type) || empty($device->vm_host_id);
                        })->values();
                    @endphp

                    @if ($vmHosts->isNotEmpty() || $rootDevices->isNotEmpty())
                        @foreach ($vmHosts as $host)
                            <tr class="cursor-pointer border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]"
                                @click="openHostId = openHostId === {{ $host->id }} ? null : {{ $host->id }}">
                                <td class="truncate px-2 py-2 text-xs font-medium text-gray-800 dark:text-white/90" title="{{ $host->server_name }}">
                                    <span class="mr-1">{{ $host->server_name }}</span>
                                    <span class="text-[10px] text-brand-600 dark:text-brand-300">({{ $host->hostedVms->count() }} VM)</span>
                                </td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $host->device_type ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">-</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $host->ip_address ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $host->vlan ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $host->os ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $host->cpu_cores ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $host->ram_gb ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $host->storage_gb ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $host->site ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $host->status ?: '-' }}</td>
                                <td class="px-2 py-2 text-xs" @click.stop>
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                            @click="openEditModal($el)"
                                            data-id="{{ $host->id }}"
                                            data-server-name="{{ $host->server_name }}"
                                            data-device-type="{{ $host->device_type }}"
                                            data-host-server="{{ $host->host_server }}"
                                            data-vm-host-id="{{ $host->vm_host_id }}"
                                            data-ip-address="{{ $host->ip_address }}"
                                            data-vlan="{{ $host->vlan }}"
                                            data-nic-model="{{ $host->nic_model }}"
                                            data-os="{{ $host->os }}"
                                            data-cpu-cores="{{ $host->cpu_cores }}"
                                            data-ram-gb="{{ $host->ram_gb }}"
                                            data-storage-gb="{{ $host->storage_gb }}"
                                            data-objid-cpu="{{ $host->objid_cpu }}"
                                            data-objid-ram="{{ $host->objid_ram }}"
                                            data-objid-ping="{{ $host->objid_ping }}"
                                            data-objid-diskfree="{{ $host->objid_diskfree }}"
                                            data-objid-traffic="{{ $host->objid_traffic }}"
                                            data-site="{{ $host->site }}"
                                            data-system-role="{{ $host->system_role }}"
                                            data-environment="{{ $host->environment }}"
                                            data-owner-team="{{ $host->owner_team }}"
                                            data-status="{{ $host->status }}"
                                            data-notes="{{ $host->notes }}"
                                            class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.aset-ti.perangkat-dc-drc.delete', $host) }}"
                                            onsubmit="return confirm('Hapus perangkat ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex h-8 items-center rounded-lg border border-red-400 px-2 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <tr x-show="openHostId === {{ $host->id }}" x-transition.opacity class="border-b border-gray-100 dark:border-gray-800">
                                <td colspan="12" class="px-3 pb-3 pt-1">
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-white/[0.02]">
                                        <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Daftar VM pada host {{ $host->server_name }}</h4>
                                        @if ($host->hostedVms->isEmpty())
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Belum ada VM yang terhubung ke host ini.</p>
                                        @else
                                            <div class="space-y-2">
                                                @foreach ($host->hostedVms as $vm)
                                                    <div class="content-card-tight px-3 py-2">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <div class="min-w-0">
                                                                <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ $vm->server_name }}</p>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                    IP: {{ $vm->ip_address ?: '-' }} | OS: {{ $vm->os ?: '-' }} | CPU: {{ $vm->cpu_cores ?: '-' }} | RAM: {{ $vm->ram_gb ?: '-' }} GB
                                                                </p>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <button type="button"
                                                                    @click="openEditModal($el)"
                                                                    data-id="{{ $vm->id }}"
                                                                    data-server-name="{{ $vm->server_name }}"
                                                                    data-device-type="{{ $vm->device_type }}"
                                                                    data-host-server="{{ $vm->host_server }}"
                                                                    data-vm-host-id="{{ $vm->vm_host_id }}"
                                                                    data-ip-address="{{ $vm->ip_address }}"
                                                                    data-vlan="{{ $vm->vlan }}"
                                                                    data-nic-model="{{ $vm->nic_model }}"
                                                                    data-os="{{ $vm->os }}"
                                                                    data-cpu-cores="{{ $vm->cpu_cores }}"
                                                                    data-ram-gb="{{ $vm->ram_gb }}"
                                                                    data-storage-gb="{{ $vm->storage_gb }}"
                                                                    data-objid-cpu="{{ $vm->objid_cpu }}"
                                                                    data-objid-ram="{{ $vm->objid_ram }}"
                                                                    data-objid-ping="{{ $vm->objid_ping }}"
                                                                    data-objid-diskfree="{{ $vm->objid_diskfree }}"
                                                                    data-objid-traffic="{{ $vm->objid_traffic }}"
                                                                    data-site="{{ $vm->site }}"
                                                                    data-system-role="{{ $vm->system_role }}"
                                                                    data-environment="{{ $vm->environment }}"
                                                                    data-owner-team="{{ $vm->owner_team }}"
                                                                    data-status="{{ $vm->status }}"
                                                                    data-notes="{{ $vm->notes }}"
                                                                    class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                                                    Edit
                                                                </button>
                                                                <form method="POST" action="{{ route('admin.aset-ti.perangkat-dc-drc.delete', $vm) }}"
                                                                    onsubmit="return confirm('Hapus perangkat ini?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit"
                                                                        class="inline-flex h-8 items-center rounded-lg border border-red-400 px-2 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20">
                                                                        Hapus
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        @foreach ($rootDevices as $device)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->server_name }}">{{ $device->server_name }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->device_type ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->vmHost?->server_name ?? ($device->host_server ?: '-') }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->ip_address ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->vlan ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->os }}">{{ $device->os ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->cpu_cores ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->ram_gb ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->storage_gb ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->site ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->status ?: '-' }}</td>
                                <td class="px-2 py-2 text-xs">
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                            @click="openEditModal($el)"
                                            data-id="{{ $device->id }}"
                                            data-server-name="{{ $device->server_name }}"
                                            data-device-type="{{ $device->device_type }}"
                                            data-host-server="{{ $device->host_server }}"
                                            data-vm-host-id="{{ $device->vm_host_id }}"
                                            data-ip-address="{{ $device->ip_address }}"
                                            data-vlan="{{ $device->vlan }}"
                                            data-nic-model="{{ $device->nic_model }}"
                                            data-os="{{ $device->os }}"
                                            data-cpu-cores="{{ $device->cpu_cores }}"
                                            data-ram-gb="{{ $device->ram_gb }}"
                                            data-storage-gb="{{ $device->storage_gb }}"
                                            data-objid-cpu="{{ $device->objid_cpu }}"
                                            data-objid-ram="{{ $device->objid_ram }}"
                                            data-objid-ping="{{ $device->objid_ping }}"
                                            data-objid-diskfree="{{ $device->objid_diskfree }}"
                                            data-objid-traffic="{{ $device->objid_traffic }}"
                                            data-site="{{ $device->site }}"
                                            data-system-role="{{ $device->system_role }}"
                                            data-environment="{{ $device->environment }}"
                                            data-owner-team="{{ $device->owner_team }}"
                                            data-status="{{ $device->status }}"
                                            data-notes="{{ $device->notes }}"
                                            class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.aset-ti.perangkat-dc-drc.delete', $device) }}"
                                            onsubmit="return confirm('Hapus perangkat ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex h-8 items-center rounded-lg border border-red-400 px-2 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-500 dark:text-red-300 dark:hover:bg-red-900/20">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="12" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Belum ada data perangkat DC/DRC.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div x-show="showImportModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showImportModal = false">
            <div class="w-full max-w-2xl content-card p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Import Perangkat DC/DRC (Excel)</h4>
                    <button type="button" @click="showImportModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form method="POST" action="{{ route('admin.aset-ti.perangkat-dc-drc.import') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Gunakan file `.xlsx/.xls/.csv` sesuai format template import perangkat DC/DRC.
                    </p>
                    <input type="file" name="import_file" required accept=".xlsx,.xls,.csv"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-white dark:border-gray-700 dark:text-white/90" />
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ route('admin.aset-ti.perangkat-dc-drc.template') }}" data-no-transition
                            class="inline-flex h-10 items-center rounded-lg border border-emerald-500 px-4 text-sm font-medium text-emerald-600 hover:bg-emerald-50 dark:border-emerald-400 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                            Download Template
                        </a>
                        <button type="submit"
                            class="inline-flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm font-medium text-white hover:bg-amber-600">
                            Import Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showAddForm" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showAddForm = false">
            <div class="max-h-[90vh] w-full max-w-6xl overflow-y-auto content-card p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tambah Perangkat DC/DRC</h4>
                    <button type="button" @click="showAddForm = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form method="POST" action="{{ route('admin.aset-ti.perangkat-dc-drc.store') }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    @csrf
                    @include('pages.dashboard.aset-ti.partials.dc-drc-form-fields', ['vmHosts' => $vmHosts])
                    <div class="md:col-span-4">
                        <button type="submit"
                            class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                            Simpan Perangkat
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showEditModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showEditModal = false">
            <div class="max-h-[90vh] w-full max-w-6xl overflow-y-auto content-card p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Edit Perangkat DC/DRC</h4>
                    <button type="button" @click="showEditModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form :action="editAction" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')
                    @include('pages.dashboard.aset-ti.partials.dc-drc-form-fields-vertical', ['vmHosts' => $vmHosts])
                    <div>
                        <button type="submit"
                            class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                            Simpan Update Perangkat
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
