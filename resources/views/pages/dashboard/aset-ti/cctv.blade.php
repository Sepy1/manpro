@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="Aset TI - CCTV" />
    @php
        $nextSortDir = function (string $field) use ($sortBy, $sortDir): string {
            return $sortBy === $field && $sortDir === 'asc' ? 'desc' : 'asc';
        };
        $sortUrl = function (string $field) use ($nextSortDir): string {
            return route('admin.aset-ti.cctv.index', array_merge(request()->except('page', 'sort_by', 'sort_dir'), [
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

    <div class="flex min-h-0 h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6"
        x-data="{
            showAddForm: @js($errors->any() && old('branch')),
            showImportModal: @js($errors->has('import_file')),
            showEditModal: false,
            updateActionBase: @js(route('admin.aset-ti.cctv.update', ['device' => '__ID__'])),
            editForm: {
                id: '',
                branch: '',
                office: '',
                dvr_brand: '',
                channel_count: '',
                harddisk: '',
                monitor: '',
                connection_status: '',
                device_status: '',
                notes: '',
                dvr_photo_url: '',
                monitor_photo_url: '',
            },
            openEditModal(el) {
                this.editForm = {
                    id: el.dataset.id || '',
                    branch: el.dataset.branch || '',
                    office: el.dataset.office || '',
                    dvr_brand: el.dataset.dvrBrand || '',
                    channel_count: el.dataset.channelCount || '',
                    harddisk: el.dataset.harddisk || '',
                    monitor: el.dataset.monitor || '',
                    connection_status: el.dataset.connectionStatus || '',
                    device_status: el.dataset.deviceStatus || '',
                    notes: el.dataset.notes || '',
                    dvr_photo_url: el.dataset.dvrPhotoUrl || '',
                    monitor_photo_url: el.dataset.monitorPhotoUrl || '',
                };
                this.showEditModal = true;
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

        <div class="flex min-h-0 flex-1 flex-col">
            <div class="mb-4 space-y-3">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Manajemen Perangkat CCTV</h3>
                <form method="GET" action="{{ route('admin.aset-ti.cctv.index') }}"
                    class="flex items-center gap-2 overflow-x-auto pb-1">
                    <input type="text" name="keyword" value="{{ $filters['keyword'] }}"
                        placeholder="Filter semua field (cabang, kantor kas, merk DVR, koneksi, status, dst)"
                        class="h-10 w-[280px] shrink-0 rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <button type="submit"
                        class="inline-flex h-10 shrink-0 items-center rounded-lg border border-brand-500 px-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                        Filter
                    </button>
                    <a href="{{ route('admin.aset-ti.cctv.index') }}"
                        class="inline-flex h-10 shrink-0 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/10">
                        Reset
                    </a>
                    <button type="button" @click="showImportModal = true"
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-amber-500 px-4 text-sm font-medium text-amber-600 hover:bg-amber-50 dark:border-amber-400 dark:text-amber-300 dark:hover:bg-amber-900/20">
                        Import Excel
                    </button>
                    <a href="{{ route('admin.aset-ti.cctv.export') }}" data-no-transition
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-blue-500 px-4 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:border-blue-400 dark:text-blue-300 dark:hover:bg-blue-900/20">
                        Export Excel
                    </a>
                    <button type="button" @click="showAddForm = true"
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Add CCTV
                    </button>
                    <button type="button"
                        onclick="if (confirm('Hapus semua data CCTV? Tindakan ini tidak dapat dibatalkan.')) document.getElementById('delete-all-cctv-form').submit();"
                        class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-red-500 px-4 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-400 dark:text-red-300 dark:hover:bg-red-900/20">
                        Delete All
                    </button>
                </form>
            </div>
            <form id="delete-all-cctv-form" method="POST" action="{{ route('admin.aset-ti.cctv.delete-all') }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <table class="w-full table-fixed border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="w-[8%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <a href="{{ $sortUrl('branch') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Cabang <span>{{ $sortIndicator('branch') }}</span></a>
                            </th>
                            <th class="w-[8%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <a href="{{ $sortUrl('office') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Kantor Kas <span>{{ $sortIndicator('office') }}</span></a>
                            </th>
                            <th class="w-[9%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <a href="{{ $sortUrl('dvr_brand') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Merk DVR <span>{{ $sortIndicator('dvr_brand') }}</span></a>
                            </th>
                            <th class="w-[7%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <a href="{{ $sortUrl('channel_count') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Jumlah Channel <span>{{ $sortIndicator('channel_count') }}</span></a>
                            </th>
                            <th class="w-[8%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <a href="{{ $sortUrl('harddisk') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Harddisk <span>{{ $sortIndicator('harddisk') }}</span></a>
                            </th>
                            <th class="w-[8%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <a href="{{ $sortUrl('monitor') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Monitor <span>{{ $sortIndicator('monitor') }}</span></a>
                            </th>
                            <th class="w-[8%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <a href="{{ $sortUrl('connection_status') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Koneksi <span>{{ $sortIndicator('connection_status') }}</span></a>
                            </th>
                            <th class="w-[8%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <a href="{{ $sortUrl('device_status') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Status <span>{{ $sortIndicator('device_status') }}</span></a>
                            </th>
                            <th class="w-[12%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <a href="{{ $sortUrl('notes') }}" class="inline-flex items-center gap-1 hover:text-brand-500">Keterangan <span>{{ $sortIndicator('notes') }}</span></a>
                            </th>
                            <th class="w-[6%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Foto DVR</th>
                            <th class="w-[7%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Foto Monitor</th>
                            <th class="w-[9%] px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            @php
                                $dvrPhotoUrl = $device->dvr_photo_path ? Storage::url($device->dvr_photo_path) : null;
                                $monitorPhotoUrl = $device->monitor_photo_path ? Storage::url($device->monitor_photo_path) : null;
                            @endphp
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->branch }}">{{ $device->branch }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->office }}">{{ $device->office }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->dvr_brand }}">{{ $device->dvr_brand }}</td>
                                <td class="px-2 py-2 text-xs text-gray-700 dark:text-gray-300">{{ $device->channel_count ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->harddisk }}">{{ $device->harddisk }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->monitor }}">{{ $device->monitor }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->connection_status }}">{{ $device->connection_status ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->device_status }}">{{ $device->device_status ?: '-' }}</td>
                                <td class="truncate px-2 py-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $device->notes }}">{{ $device->notes ?: '-' }}</td>
                                <td class="px-2 py-2 text-xs">
                                    @if ($dvrPhotoUrl)
                                        <a href="{{ $dvrPhotoUrl }}" target="_blank" class="text-brand-500 hover:underline">
                                            Lihat
                                        </a>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-xs">
                                    @if ($monitorPhotoUrl)
                                        <a href="{{ $monitorPhotoUrl }}" target="_blank" class="text-brand-500 hover:underline">
                                            Lihat
                                        </a>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-xs">
                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                            @click="openEditModal($el)"
                                            data-id="{{ $device->id }}"
                                            data-branch="{{ $device->branch }}"
                                            data-office="{{ $device->office }}"
                                            data-dvr-brand="{{ $device->dvr_brand }}"
                                            data-channel-count="{{ $device->channel_count }}"
                                            data-harddisk="{{ $device->harddisk }}"
                                            data-monitor="{{ $device->monitor }}"
                                            data-connection-status="{{ $device->connection_status }}"
                                            data-device-status="{{ $device->device_status }}"
                                            data-notes="{{ $device->notes }}"
                                            data-dvr-photo-url="{{ $dvrPhotoUrl }}"
                                            data-monitor-photo-url="{{ $monitorPhotoUrl }}"
                                            class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-400 dark:text-white/90 dark:hover:bg-brand-500/10">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.aset-ti.cctv.delete', $device) }}"
                                            onsubmit="return confirm('Hapus perangkat CCTV ini?')">
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
                        @empty
                            <tr>
                                <td colspan="12" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada data perangkat CCTV.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>

        <div x-show="showImportModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showImportModal = false">
            <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Import Data CCTV (Excel)</h4>
                    <button type="button" @click="showImportModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form method="POST" action="{{ route('admin.aset-ti.cctv.import') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Gunakan file `.xlsx/.xls/.csv` dengan format kolom sesuai template import.
                    </p>
                    <input type="file" name="import_file" required accept=".xlsx,.xls,.csv"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-white dark:border-gray-700 dark:text-white/90" />
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ route('admin.aset-ti.cctv.template') }}" data-no-transition
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
            <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tambah Perangkat CCTV</h4>
                    <button type="button" @click="showAddForm = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>
                <form method="POST" action="{{ route('admin.aset-ti.cctv.store') }}"
                    enctype="multipart/form-data"
                    class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    @csrf
                    <input type="text" name="branch" value="{{ old('branch') }}" required placeholder="Cabang"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="office" value="{{ old('office') }}" required placeholder="Kantor kas"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="dvr_brand" value="{{ old('dvr_brand') }}" required placeholder="Merk DVR"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="number" name="channel_count" value="{{ old('channel_count') }}" min="1" placeholder="Jumlah channel"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="harddisk" value="{{ old('harddisk') }}" required placeholder="Harddisk"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="monitor" value="{{ old('monitor') }}" required placeholder="Monitor"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="connection_status" value="{{ old('connection_status') }}" placeholder="Koneksi (bebas)"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="device_status" value="{{ old('device_status') }}" placeholder="Status (bebas)"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Lampiran Foto DVR</label>
                        <input type="file" name="dvr_photo" accept="image/*"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-white dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Lampiran Foto Monitor</label>
                        <input type="file" name="monitor_photo" accept="image/*"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-white dark:border-gray-700 dark:text-white/90" />
                    </div>
                    <div class="md:col-span-4">
                        <textarea name="notes" rows="2" placeholder="Keterangan"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">{{ old('notes') }}</textarea>
                    </div>
                    <div class="md:col-span-4">
                        <button type="submit"
                            class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                            Simpan CCTV
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showEditModal" x-cloak x-transition class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 p-4" @click.self="showEditModal = false">
            <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">Edit Perangkat CCTV</h4>
                    <button type="button" @click="showEditModal = false" class="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10">Tutup</button>
                </div>

                <form :action="editAction" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    @csrf
                    @method('PUT')
                    <input type="text" name="branch" x-model="editForm.branch" required placeholder="Cabang"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="office" x-model="editForm.office" required placeholder="Kantor kas"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="dvr_brand" x-model="editForm.dvr_brand" required placeholder="Merk DVR"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="number" name="channel_count" x-model="editForm.channel_count" min="1" placeholder="Jumlah channel"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="harddisk" x-model="editForm.harddisk" required placeholder="Harddisk"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="monitor" x-model="editForm.monitor" required placeholder="Monitor"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="connection_status" x-model="editForm.connection_status" placeholder="Koneksi (bebas)"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <input type="text" name="device_status" x-model="editForm.device_status" placeholder="Status (bebas)"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Ganti Foto DVR (opsional)</label>
                        <input type="file" name="dvr_photo" accept="image/*"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-white dark:border-gray-700 dark:text-white/90" />
                        <template x-if="editForm.dvr_photo_url">
                            <a :href="editForm.dvr_photo_url" target="_blank" class="mt-1 inline-block text-xs text-brand-500 hover:underline">Lihat foto DVR saat ini</a>
                        </template>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Ganti Foto Monitor (opsional)</label>
                        <input type="file" name="monitor_photo" accept="image/*"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-white dark:border-gray-700 dark:text-white/90" />
                        <template x-if="editForm.monitor_photo_url">
                            <a :href="editForm.monitor_photo_url" target="_blank" class="mt-1 inline-block text-xs text-brand-500 hover:underline">Lihat foto monitor saat ini</a>
                        </template>
                    </div>
                    <div class="md:col-span-4">
                        <textarea name="notes" rows="2" x-model="editForm.notes" placeholder="Keterangan"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                    </div>
                    <div class="md:col-span-4">
                        <button type="submit"
                            class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                            Simpan Update CCTV
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
