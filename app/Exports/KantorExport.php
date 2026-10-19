<?php

namespace App\Exports;

use App\Models\Kantor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class KantorExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        $out = [];

        Kantor::query()
            ->with(['kasKantor' => fn ($q) => $q->orderBy('kode_kas')])
            ->orderBy('kode_kantor')
            ->get()
            ->each(function (Kantor $kantor) use (&$out) {
                $aktifCab = $kantor->is_active ? 'YA' : 'TIDAK';

                if ($kantor->kasKantor->isEmpty()) {
                    $out[] = [
                        'kode_kantor' => $kantor->kode_kantor,
                        'nama_kantor' => $kantor->nama_kantor,
                        'aktif_kantor' => $aktifCab,
                        'latitude_kantor' => $kantor->latitude,
                        'longitude_kantor' => $kantor->longitude,
                        'wireless_ip_kantor' => $kantor->wireless_ip,
                        'status_ping_kantor' => $kantor->ping_status,
                        'latency_ping_kantor_ms' => $kantor->ping_latency_ms,
                        'ping_diperiksa_kantor' => $kantor->ping_checked_at?->format('Y-m-d H:i:s'),
                        'kode_kas' => '',
                        'nama_kas' => '',
                        'aktif_kas' => '',
                        'latitude_kas' => '',
                        'longitude_kas' => '',
                        'wireless_ip_kas' => '',
                        'status_ping_kas' => '',
                        'latency_ping_kas_ms' => '',
                        'ping_diperiksa_kas' => '',
                    ];

                    return;
                }

                foreach ($kantor->kasKantor as $kas) {
                    $out[] = [
                        'kode_kantor' => $kantor->kode_kantor,
                        'nama_kantor' => $kantor->nama_kantor,
                        'aktif_kantor' => $aktifCab,
                        'latitude_kantor' => $kantor->latitude,
                        'longitude_kantor' => $kantor->longitude,
                        'wireless_ip_kantor' => $kantor->wireless_ip,
                        'status_ping_kantor' => $kantor->ping_status,
                        'latency_ping_kantor_ms' => $kantor->ping_latency_ms,
                        'ping_diperiksa_kantor' => $kantor->ping_checked_at?->format('Y-m-d H:i:s'),
                        'kode_kas' => $kas->kode_kas,
                        'nama_kas' => $kas->nama_kas,
                        'aktif_kas' => $kas->is_active ? 'YA' : 'TIDAK',
                        'latitude_kas' => $kas->latitude,
                        'longitude_kas' => $kas->longitude,
                        'wireless_ip_kas' => $kas->wireless_ip,
                        'status_ping_kas' => $kas->ping_status,
                        'latency_ping_kas_ms' => $kas->ping_latency_ms,
                        'ping_diperiksa_kas' => $kas->ping_checked_at?->format('Y-m-d H:i:s'),
                    ];
                }
            });

        return new Collection($out);
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
