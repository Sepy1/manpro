@php
    $vmHosts = $vmHosts ?? collect();
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
    <table class="w-full border-collapse">
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="w-56 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">Server Name</td>
                <td class="px-4 py-3">
                    <input type="text" name="server_name" x-model="editForm.server_name" required
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">Tipe</td>
                <td class="px-4 py-3">
                    <select name="device_type" x-model="editForm.device_type"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                        <option value="">Pilih Tipe</option>
                        <option value="VM">VM</option>
                        <option value="vm host">vm host</option>
                        <option value="bare metal">bare metal</option>
                        <option value="physical">physical</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">VM Server</td>
                <td class="px-4 py-3">
                    <select name="vm_host_id" x-model="editForm.vm_host_id"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90">
                        <option value="">Pilih VM Server (khusus tipe VM)</option>
                        @foreach ($vmHosts as $vmHost)
                            <option value="{{ $vmHost->id }}">
                                {{ $vmHost->server_name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Jika tipe perangkat adalah VM, pilih VM server dari perangkat bertipe vm host.
                    </p>
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">VM Server (teks)</td>
                <td class="px-4 py-3">
                    <input type="text" name="host_server" x-model="editForm.host_server"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">IP Address</td>
                <td class="px-4 py-3">
                    <input type="text" name="ip_address" x-model="editForm.ip_address"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">VLAN</td>
                <td class="px-4 py-3">
                    <input type="text" name="vlan" x-model="editForm.vlan"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">NIC Model</td>
                <td class="px-4 py-3">
                    <input type="text" name="nic_model" x-model="editForm.nic_model"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">OS</td>
                <td class="px-4 py-3">
                    <input type="text" name="os" x-model="editForm.os"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">CPU(s)</td>
                <td class="px-4 py-3">
                    <input type="number" name="cpu_cores" x-model="editForm.cpu_cores" min="1"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">RAM (GB)</td>
                <td class="px-4 py-3">
                    <input type="number" name="ram_gb" x-model="editForm.ram_gb" min="1"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">Hardisk (GB)</td>
                <td class="px-4 py-3">
                    <input type="number" name="storage_gb" x-model="editForm.storage_gb" min="1"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">OBJID CPU</td>
                <td class="px-4 py-3">
                    <input type="text" name="objid_cpu" x-model="editForm.objid_cpu"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">OBJID RAM</td>
                <td class="px-4 py-3">
                    <input type="text" name="objid_ram" x-model="editForm.objid_ram"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">OBJID Ping</td>
                <td class="px-4 py-3">
                    <input type="text" name="objid_ping" x-model="editForm.objid_ping"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">OBJID DiskFree</td>
                <td class="px-4 py-3">
                    <input type="text" name="objid_diskfree" x-model="editForm.objid_diskfree"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">OBJID Traffic</td>
                <td class="px-4 py-3">
                    <input type="text" name="objid_traffic" x-model="editForm.objid_traffic"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">Lokasi (DC/DRC)</td>
                <td class="px-4 py-3">
                    <input type="text" name="site" x-model="editForm.site"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">Fungsi Sistem</td>
                <td class="px-4 py-3">
                    <input type="text" name="system_role" x-model="editForm.system_role"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">Environment</td>
                <td class="px-4 py-3">
                    <input type="text" name="environment" x-model="editForm.environment"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">Owner Team</td>
                <td class="px-4 py-3">
                    <input type="text" name="owner_team" x-model="editForm.owner_team"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">Status</td>
                <td class="px-4 py-3">
                    <input type="text" name="status" x-model="editForm.status"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90" />
                </td>
            </tr>
            <tr>
                <td class="bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 dark:bg-gray-800/40 dark:text-gray-200">Keterangan</td>
                <td class="px-4 py-3">
                    <textarea name="notes" rows="3" x-model="editForm.notes"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
                </td>
            </tr>
        </tbody>
    </table>
</div>
