<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VpnClientsTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return ['Nama Pengguna', 'Username', 'Common Name', 'Departemen', 'Kantor Cabang', 'Nomor Inventaris', 'IP VPN', 'Group VPN', 'Allowed IP Destination', 'Status'];
    }

    public function array(): array
    {
        return [[
            'Nama Contoh', 'nama.contoh', 'laptop-contoh', 'TI', 'Kantor Pusat',
            'INV-001', '', '', '192.176.1.10, 192.176.1.0/28', 'active',
        ]];
    }
}
