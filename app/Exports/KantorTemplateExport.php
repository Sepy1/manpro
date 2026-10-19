<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class KantorTemplateExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return new Collection([
            [
                'kode_kantor' => '001',
                'nama_kantor' => 'Cabang Semarang',
                'aktif_kantor' => 'YA',
                'latitude_kantor' => -6.966667,
                'longitude_kantor' => 110.416664,
                'wireless_ip_kantor' => '10.10.1.1',
                'status_ping_kantor' => '',
                'latency_ping_kantor_ms' => '',
                'ping_diperiksa_kantor' => '',
                'kode_kas' => 'KK-01',
                'nama_kas' => 'KK Simpang Lima',
                'aktif_kas' => 'YA',
                'latitude_kas' => -6.990398,
                'longitude_kas' => 110.422910,
                'wireless_ip_kas' => '10.10.1.2',
                'status_ping_kas' => '',
                'latency_ping_kas_ms' => '',
                'ping_diperiksa_kas' => '',
            ],
            [
                'kode_kantor' => '001',
                'nama_kantor' => 'Cabang Semarang',
                'aktif_kantor' => 'YA',
                'latitude_kantor' => -6.966667,
                'longitude_kantor' => 110.416664,
                'wireless_ip_kantor' => '10.10.1.1',
                'status_ping_kantor' => '',
                'latency_ping_kantor_ms' => '',
                'ping_diperiksa_kantor' => '',
                'kode_kas' => 'KK-02',
                'nama_kas' => 'KK Ungaran',
                'aktif_kas' => 'YA',
                'latitude_kas' => -7.139722,
                'longitude_kas' => 110.405000,
                'wireless_ip_kas' => '10.10.1.3',
                'status_ping_kas' => '',
                'latency_ping_kas_ms' => '',
                'ping_diperiksa_kas' => '',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'kode_kantor', 'nama_kantor', 'aktif_kantor',
            'latitude_kantor', 'longitude_kantor', 'wireless_ip_kantor',
            'status_ping_kantor', 'latency_ping_kantor_ms', 'ping_diperiksa_kantor',
            'kode_kas', 'nama_kas', 'aktif_kas',
            'latitude_kas', 'longitude_kas', 'wireless_ip_kas',
            'status_ping_kas', 'latency_ping_kas_ms', 'ping_diperiksa_kas',
        ];
    }
}
