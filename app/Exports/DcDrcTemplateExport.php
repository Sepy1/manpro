<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DcDrcTemplateExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return new Collection([
            [
                'server_name' => 'VM 1.30',
                'tipe' => 'VM',
                'vm_server' => 'VM 1.30',
                'ip_address' => '192.176.130.17',
                'vlan' => '1',
                'nic_model' => 'vm host',
                'os' => 'Ubuntu 2',
                'cpu' => 3,
                'ram_gb' => 16,
                'hardisk_gb' => 1000,
                'objid_cpu' => '1001',
                'objid_ram' => '1002',
                'objid_ping' => '1003',
                'objid_diskfree' => '1004',
                'objid_traffic' => '1005',
                'lokasi' => 'DC',
                'fungsi' => 'database',
                'environment' => 'production',
                'owner_team' => 'Infra',
                'status' => 'active',
                'keterangan' => 'Contoh data VM host',
            ],
            [
                'server_name' => 'server 55',
                'tipe' => 'vm host',
                'vm_server' => 'VM 55',
                'ip_address' => '192.176.1.55',
                'vlan' => '1',
                'nic_model' => 'vm host',
                'os' => 'Windows Server 2012',
                'cpu' => 3,
                'ram_gb' => 16,
                'hardisk_gb' => 700,
                'objid_cpu' => '2001',
                'objid_ram' => '2002',
                'objid_ping' => '2003',
                'objid_diskfree' => '2004',
                'objid_traffic' => '2005',
                'lokasi' => 'DRC',
                'fungsi' => 'database, waszu',
                'environment' => 'production',
                'owner_team' => 'Infra',
                'status' => 'active',
                'keterangan' => 'Contoh data dari daftar perangkat',
            ],
        ]);
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
