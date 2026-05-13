<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CctvTemplateExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return new Collection([
            [
                'cabang' => 'Semarang',
                'kantor_kas' => 'KK Tembalang',
                'merk_dvr' => 'Hikvision',
                'jumlah_channel' => 16,
                'harddisk' => '2TB',
                'monitor' => '24 inch',
                'koneksi' => 'online',
                'status' => 'normal',
                'keterangan' => 'Contoh data import',
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'cabang',
            'kantor_kas',
            'merk_dvr',
            'jumlah_channel',
            'harddisk',
            'monitor',
            'koneksi',
            'status',
            'keterangan',
        ];
    }
}
