@php
    $isEdit = $isEdit ?? false;
    $vmHosts = $vmHosts ?? collect();
@endphp

<input type="text" name="server_name" value="{{ $isEdit ? '' : old('server_name') }}" {{ $isEdit ? 'x-model=editForm.server_name' : '' }} required placeholder="Server Name"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<select name="device_type" {{ $isEdit ? 'x-model=editForm.device_type' : '' }}
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
    <option value="">Pilih Tipe</option>
    <option value="VM" @selected(!$isEdit && old('device_type') === 'VM')>VM</option>
    <option value="vm host" @selected(!$isEdit && old('device_type') === 'vm host')>vm host</option>
    <option value="bare metal" @selected(!$isEdit && old('device_type') === 'bare metal')>bare metal</option>
    <option value="physical" @selected(!$isEdit && old('device_type') === 'physical')>physical</option>
</select>

<div>
    <select name="vm_host_id" {{ $isEdit ? 'x-model=editForm.vm_host_id' : '' }}
        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
        <option value="">Pilih VM Server (khusus tipe VM)</option>
        @foreach ($vmHosts as $vmHost)
            <option value="{{ $vmHost->id }}" @selected(!$isEdit && old('vm_host_id') == $vmHost->id)>
                {{ $vmHost->server_name }}
            </option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        Jika tipe perangkat adalah VM, pilih VM server dari perangkat bertipe vm host.
    </p>
</div>

<input type="text" name="host_server" value="{{ $isEdit ? '' : old('host_server') }}" {{ $isEdit ? 'x-model=editForm.host_server' : '' }} placeholder="VM Server (teks fallback/import)"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="text" name="ip_address" value="{{ $isEdit ? '' : old('ip_address') }}" {{ $isEdit ? 'x-model=editForm.ip_address' : '' }} placeholder="IP Address"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="text" name="vlan" value="{{ $isEdit ? '' : old('vlan') }}" {{ $isEdit ? 'x-model=editForm.vlan' : '' }} placeholder="VLAN"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="text" name="nic_model" value="{{ $isEdit ? '' : old('nic_model') }}" {{ $isEdit ? 'x-model=editForm.nic_model' : '' }} placeholder="NIC Model"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="text" name="os" value="{{ $isEdit ? '' : old('os') }}" {{ $isEdit ? 'x-model=editForm.os' : '' }} placeholder="OS"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="number" name="cpu_cores" value="{{ $isEdit ? '' : old('cpu_cores') }}" {{ $isEdit ? 'x-model=editForm.cpu_cores' : '' }} min="1" placeholder="CPU(s)"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="number" name="ram_gb" value="{{ $isEdit ? '' : old('ram_gb') }}" {{ $isEdit ? 'x-model=editForm.ram_gb' : '' }} min="1" placeholder="RAM (GB)"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="number" name="storage_gb" value="{{ $isEdit ? '' : old('storage_gb') }}" {{ $isEdit ? 'x-model=editForm.storage_gb' : '' }} min="1" placeholder="Hardisk (GB)"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="text" name="site" value="{{ $isEdit ? '' : old('site') }}" {{ $isEdit ? 'x-model=editForm.site' : '' }} placeholder="Lokasi (DC/DRC)"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="text" name="system_role" value="{{ $isEdit ? '' : old('system_role') }}" {{ $isEdit ? 'x-model=editForm.system_role' : '' }} placeholder="Fungsi Sistem"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="text" name="environment" value="{{ $isEdit ? '' : old('environment') }}" {{ $isEdit ? 'x-model=editForm.environment' : '' }} placeholder="Environment (prod/stg/dev)"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="text" name="owner_team" value="{{ $isEdit ? '' : old('owner_team') }}" {{ $isEdit ? 'x-model=editForm.owner_team' : '' }} placeholder="Owner Team"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<input type="text" name="status" value="{{ $isEdit ? '' : old('status') }}" {{ $isEdit ? 'x-model=editForm.status' : '' }} placeholder="Status"
    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
<div class="md:col-span-4">
    <textarea name="notes" rows="3" {{ $isEdit ? 'x-model=editForm.notes' : '' }} placeholder="Keterangan"
        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">{{ $isEdit ? '' : old('notes') }}</textarea>
</div>
