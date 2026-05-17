<?php

namespace App\Exports;

use App\Models\DcDrcDevice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DcDrcDevicesExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return DcDrcDevice::query()
            ->with('vmHost:id,server_name')
            ->orderBy('id')
            ->get()
            ->map(function (DcDrcDevice $device) {
                return [
                    'server_name' => $device->server_name,
                    'tipe' => $device->device_type,
                    'vm_server' => $device->vmHost?->server_name ?? $device->host_server,
                    'ip_address' => $device->ip_address,
                    'vlan' => $device->vlan,
                    'nic_model' => $device->nic_model,
                    'os' => $device->os,
                    'cpu' => $device->cpu_cores,
                    'ram_gb' => $device->ram_gb,
                    'hardisk_gb' => $device->storage_gb,
                    'objid_cpu' => $device->objid_cpu,
                    'objid_ram' => $device->objid_ram,
                    'objid_ping' => $device->objid_ping,
                    'objid_diskfree' => $device->objid_diskfree,
                    'objid_traffic' => $device->objid_traffic,
                    'lokasi' => $device->site,
                    'fungsi' => $device->system_role,
                    'environment' => $device->environment,
                    'owner_team' => $device->owner_team,
                    'status' => $device->status,
                    'keterangan' => $device->notes,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'server_name',
            'tipe',
            'vm_server',
            'ip_address',
            'vlan',
            'nic_model',
            'os',
            'cpu',
            'ram_gb',
            'hardisk_gb',
            'objid_cpu',
            'objid_ram',
            'objid_ping',
            'objid_diskfree',
            'objid_traffic',
            'lokasi',
            'fungsi',
            'environment',
            'owner_team',
            'status',
            'keterangan',
        ];
    }
}
