<?php

namespace App\Exports;

use App\Models\CctvDevice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CctvMissingUpdateExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return CctvDevice::query()
            ->select(['branch', 'office', 'monitor', 'connection_status', 'device_status', 'notes'])
            ->where(function ($query) {
                $query->whereNull('dvr_brand')
                    ->orWhereRaw("TRIM(dvr_brand) = ''")
                    ->orWhere('dvr_brand', '=', '-');
            })
            ->orderBy('branch')
            ->orderBy('office')
            ->get()
            ->map(function (CctvDevice $row) {
                return [
                    'cabang' => $row->branch,
                    'kantor_kas' => $row->office,
                    'monitor' => $row->monitor,
                    'koneksi' => $row->connection_status,
                    'status' => $row->device_status,
                    'keterangan' => $row->notes,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'cabang',
            'kantor_kas',
            'monitor',
            'koneksi',
            'status',
            'keterangan',
        ];
    }
}
