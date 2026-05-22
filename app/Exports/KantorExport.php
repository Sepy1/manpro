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
                        'kode_kas' => '',
                        'nama_kas' => '',
                        'aktif_kas' => '',
                    ];

                    return;
                }

                foreach ($kantor->kasKantor as $kas) {
                    $out[] = [
                        'kode_kantor' => $kantor->kode_kantor,
                        'nama_kantor' => $kantor->nama_kantor,
                        'aktif_kantor' => $aktifCab,
                        'kode_kas' => $kas->kode_kas,
                        'nama_kas' => $kas->nama_kas,
                        'aktif_kas' => $kas->is_active ? 'YA' : 'TIDAK',
                    ];
                }
            });

        return new Collection($out);
    }

    public function headings(): array
    {
        return ['kode_kantor', 'nama_kantor', 'aktif_kantor', 'kode_kas', 'nama_kas', 'aktif_kas'];
    }
}
