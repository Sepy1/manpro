@extends('layouts.admin')

@section('admin-content')
    <x-common.page-breadcrumb pageTitle="End Point - Inventaris PC" />

    <x-dashboard.accent-card accent-index="0" shell-overflow="visible" class="flex min-h-0 h-full flex-col" padding="p-5 lg:p-6">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-900/40 dark:bg-green-900/20 dark:text-green-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">{{ $errors->first() }}</div>
        @endif

        @if ($editInventory)
            <form method="POST" action="{{ route('admin.aset-ti.end-point.update', $editInventory) }}"
                class="mb-5 rounded-xl border border-brand-200 bg-brand-50/50 p-4 dark:border-brand-900/50 dark:bg-brand-900/10"
                x-data="{ officeId: '{{ old('kantor_id', $editInventory->kantor_id) }}', cashOfficeId: '{{ old('kas_kantor_id', $editInventory->kas_kantor_id) }}' }">
                @csrf
                @method('PUT')
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h4 class="font-semibold text-gray-800 dark:text-white">Edit {{ $editInventory->hostname }}</h4>
                    <a href="{{ route('admin.aset-ti.end-point.index') }}" class="text-sm text-gray-500 hover:text-gray-800 dark:hover:text-white">Tutup</a>
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <input name="nama_user" value="{{ old('nama_user', $editInventory->nama_user) }}" required placeholder="Nama user"
                        class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                    <select name="jabatan" required class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @foreach (['Kepala Divisi', 'Kepala Cabang', 'Kepala Kantor Kas', 'Kepala Bidang', 'Kepala Sub Bidang', 'Staf / AO / Admin', 'Teller', 'Customer Service'] as $jabatan)
                            <option value="{{ $jabatan }}" @selected(old('jabatan', $editInventory->jabatan) === $jabatan)>{{ $jabatan }}</option>
                        @endforeach
                    </select>
                    <select name="kantor_id" x-model="officeId" @change="cashOfficeId = ''" required class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @foreach ($kantors as $kantor)
                            <option value="{{ $kantor->id }}">{{ $kantor->kode_kantor }} — {{ $kantor->nama_kantor }}</option>
                        @endforeach
                    </select>
                    <select name="kas_kantor_id" x-model="cashOfficeId" class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Tidak ada / Kantor induk</option>
                        @foreach ($kantors as $kantor)
                            @foreach ($kantor->kasKantor as $kas)
                                <option value="{{ $kas->id }}" x-show="officeId === '{{ $kantor->id }}'">{{ $kas->kode_kas }} — {{ $kas->nama_kas ?: 'Tanpa nama' }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <select name="status_perangkat" required class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="Inventaris" @selected(old('status_perangkat', $editInventory->status_perangkat) === 'Inventaris')>Inventaris</option>
                        <option value="Device Pribadi (BYOD)" @selected(old('status_perangkat', $editInventory->status_perangkat) === 'Device Pribadi (BYOD)')>Device Pribadi (BYOD)</option>
                    </select>
                </div>
                <div class="mt-3 flex justify-end"><button class="h-10 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">Simpan Perubahan</button></div>
            </form>
        @endif
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Data Inventaris End Point</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ number_format($filteredCount) }} dari {{ number_format($totalCount) }} perangkat.
                </p>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.aset-ti.end-point.index') }}" class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4" x-data="officeFilter()">
            <input type="text" name="keyword" value="{{ $filters['keyword'] }}"
                placeholder="Cari hostname, user, serial, IP, MAC, CPU..."
                class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />

            <select name="kantor_id" x-model="officeId" @change="cashOfficeId = ''"
                class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua kantor cabang</option>
                @foreach ($kantors as $kantor)
                    <option value="{{ $kantor->id }}">{{ $kantor->kode_kantor }} — {{ $kantor->nama_kantor }}</option>
                @endforeach
            </select>

            <select name="kas_kantor_id" x-model="cashOfficeId"
                class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua kantor kas</option>
                @foreach ($kantors as $kantor)
                    @foreach ($kantor->kasKantor as $kas)
                        <option value="{{ $kas->id }}" x-show="!officeId || officeId === '{{ $kantor->id }}'">
                            {{ $kas->kode_kas }} — {{ $kas->nama_kas ?: 'Tanpa nama' }}
                        </option>
                    @endforeach
                @endforeach
            </select>

            <select name="antivirus_status"
                class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua status antivirus</option>
                @foreach ($antivirusStatuses as $status)
                    <option value="{{ $status }}" @selected($filters['antivirus_status'] === $status)>{{ $status }}</option>
                @endforeach
            </select>

            <select name="status_perangkat"
                class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Semua status perangkat</option>
                <option value="Inventaris" @selected($filters['status_perangkat'] === 'Inventaris')>Inventaris</option>
                <option value="Device Pribadi (BYOD)" @selected($filters['status_perangkat'] === 'Device Pribadi (BYOD)')>Device Pribadi (BYOD)</option>
            </select>

            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <span class="shrink-0">Aktif dari</span>
                <input type="date" name="seen_from" value="{{ $filters['seen_from'] }}"
                    class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white/90" />
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <span class="shrink-0">sampai</span>
                <input type="date" name="seen_until" value="{{ $filters['seen_until'] }}"
                    class="h-10 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white/90" />
            </label>

            <select name="sort" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="last_seen_at" @selected($filters['sort'] === 'last_seen_at')>Urutkan: terakhir aktif</option>
                <option value="hostname" @selected($filters['sort'] === 'hostname')>Urutkan: hostname</option>
                <option value="nama_user" @selected($filters['sort'] === 'nama_user')>Urutkan: nama user</option>
                <option value="created_at" @selected($filters['sort'] === 'created_at')>Urutkan: pertama masuk</option>
            </select>

            <div class="flex gap-2">
                <select name="direction" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="desc" @selected($filters['direction'] === 'desc')>Menurun</option>
                    <option value="asc" @selected($filters['direction'] === 'asc')>Menaik</option>
                </select>
                <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Filter</button>
                <a href="{{ route('admin.aset-ti.end-point.index') }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/10">Reset</a>
            </div>
        </form>

        <div class="min-h-0 flex-1 overflow-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="min-w-[1900px] w-full border-separate border-spacing-0 text-left">
                <thead class="[&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:border-b [&_th]:border-gray-200 [&_th]:bg-gray-50 dark:[&_th]:border-gray-800 dark:[&_th]:bg-slate-900">
                    <tr class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-3">Perangkat</th><th class="px-3 py-3">Pengguna</th>
                        <th class="px-3 py-3">Kantor Cabang</th><th class="px-3 py-3">Kantor Kas</th>
                        <th class="px-3 py-3">Sistem</th><th class="px-3 py-3">CPU / RAM</th>
                        <th class="px-3 py-3">Disk</th><th class="px-3 py-3">Jaringan</th>
                        <th class="px-3 py-3">Antivirus</th><th class="px-3 py-3">Terakhir Aktif</th><th class="px-3 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inventories as $item)
                        <tr class="align-top [&_td]:border-b [&_td]:border-gray-100 dark:[&_td]:border-gray-800">
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">
                                <strong class="block text-sm text-gray-900 dark:text-white">{{ $item->hostname }}</strong>
                                <span class="block font-medium text-brand-600 dark:text-brand-400">IP: {{ $item->ip_address }}</span>
                                <span class="block">
                                    Antivirus: <strong>{{ $item->antivirus_status }}</strong>
                                    @if ($item->antivirus_name && $item->antivirus_name !== '-')
                                        — {{ $item->antivirus_name }}
                                    @endif
                                </span>
                                <span class="block">Serial: {{ $item->serial_number }}</span>
                                <span class="block max-w-[220px] truncate" title="{{ $item->machine_guid }}">GUID: {{ $item->machine_guid }}</span>
                                <span class="block max-w-[220px] truncate" title="{{ $item->mainboard_type }}">Board: {{ $item->mainboard_type }}</span>
                            </td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">
                                <strong class="block text-sm">{{ $item->nama_user }}</strong>
                                <span class="block">{{ $item->jabatan }}</span>
                                <span @class([
                                    'mt-1 inline-flex rounded-full px-2 py-1 font-medium',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' => $item->status_perangkat === 'Inventaris',
                                    'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' => $item->status_perangkat !== 'Inventaris',
                                ])>{{ $item->status_perangkat }}</span>
                            </td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300"><strong>{{ $item->kantor?->kode_kantor ?? $item->kode_kantor }}</strong><br>{{ $item->kantor?->nama_kantor ?? '-' }}</td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300">@if($item->kasKantor)<strong>{{ $item->kasKantor->kode_kas }}</strong><br>{{ $item->kasKantor->nama_kas ?: '-' }}@else<span class="text-gray-400">Kantor induk</span>@endif</td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300"><span class="block max-w-[220px]">{{ $item->os_info }}</span></td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300"><span class="block max-w-[240px]">{{ $item->cpu_name }}</span><span>{{ $item->cpu_cores }} core / {{ $item->cpu_threads }} thread · {{ $item->ram_gb }} GB</span></td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300"><strong>{{ $item->disk_free_gb }} GB bebas</strong> / {{ $item->disk_total_gb }} GB<details class="mt-1"><summary class="cursor-pointer text-brand-500">Detail</summary><span class="block max-w-[260px] whitespace-normal">{{ $item->disk_detail }}</span></details></td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300"><span class="block">IP: {{ $item->ip_address }}</span><span class="block">MAC: {{ $item->mac_address }}</span><span class="block text-gray-400">Pengirim: {{ $item->sender_ip ?: '-' }}</span></td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300"><span @class(['inline-flex rounded-full px-2 py-1 font-medium', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' => $item->antivirus_status === 'Aktif', 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $item->antivirus_status !== 'Aktif'])>{{ $item->antivirus_status }}</span><span class="mt-1 block max-w-[240px]">{{ $item->antivirus_name }}</span></td>
                            <td class="px-3 py-3 text-xs text-gray-700 dark:text-gray-300"><strong class="block">{{ $item->last_seen_at?->format('d/m/Y H:i') }}</strong><span class="text-gray-400">{{ $item->last_seen_at?->diffForHumans() }}</span></td>
                            <td class="px-3 py-3 text-xs">
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.aset-ti.end-point.index', array_merge(request()->query(), ['edit' => $item->id])) }}" class="inline-flex h-8 items-center rounded-lg border border-brand-500 px-3 font-medium text-brand-600 hover:bg-brand-50 dark:text-brand-300">Edit</a>
                                    <form method="POST" action="{{ route('admin.aset-ti.end-point.destroy', $item) }}" onsubmit="return confirm('Hapus end point {{ addslashes($item->hostname) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="h-8 rounded-lg border border-red-500 px-3 font-medium text-red-600 hover:bg-red-50 dark:text-red-300">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data inventaris yang sesuai filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($inventories->hasPages())
            <div class="mt-4">{{ $inventories->links() }}</div>
        @endif
    </x-dashboard.accent-card>

    <script>
        function officeFilter() {
            return { officeId: @js($filters['kantor_id']), cashOfficeId: @js($filters['kas_kantor_id']) };
        }
    </script>
@endsection
