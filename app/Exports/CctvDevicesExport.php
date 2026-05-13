<?php

namespace App\Exports;

use App\Models\CctvDevice;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CctvDevicesExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return CctvDevice::query()
            ->orderBy('id')
            ->get()
            ->map(function (CctvDevice $device) {
                return [
                    'cabang' => $device->branch,
                    'kantor_kas' => $device->office,
                    'merk_dvr' => $device->dvr_brand,
                    'jumlah_channel' => $device->channel_count,
                    'harddisk' => $device->harddisk,
                    'monitor' => $device->monitor,
                    'koneksi' => $device->connection_status,
                    'status' => $device->device_status,
                    'keterangan' => $device->notes,
                    'foto_dvr_url' => $device->dvr_photo_path ? Storage::url($device->dvr_photo_path) : null,
                    'foto_monitor_url' => $device->monitor_photo_path ? Storage::url($device->monitor_photo_path) : null,
                ];
            });
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
            'foto_dvr_url',
            'foto_monitor_url',
        ];
    }
}
