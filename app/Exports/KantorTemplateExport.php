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
                'kode_kas' => 'KK-01',
                'nama_kas' => 'KK Simpang Lima',
                'aktif_kas' => 'YA',
            ],
            [
                'kode_kantor' => '001',
                'nama_kantor' => 'Cabang Semarang',
                'aktif_kantor' => 'YA',
                'kode_kas' => 'KK-02',
                'nama_kas' => 'KK Ungaran',
                'aktif_kas' => 'YA',
            ],
        ]);
    }

    public function headings(): array
    {
        return ['kode_kantor', 'nama_kantor', 'aktif_kantor', 'kode_kas', 'nama_kas', 'aktif_kas'];
    }
}
