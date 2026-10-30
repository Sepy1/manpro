<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BackupMonitoringEventsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return (clone $this->query)
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return [
            'Tanggal Backup',
            'Server',
            'Database',
            'Ukuran (MB)',
            'Status',
            'Lokasi File',
        ];
    }

    public function map($event): array
    {
        return [
            $event->tanggal->format('Y-m-d H:i:s'),
            $event->server,
            $event->nama_db,
            (float) $event->ukuran,
            $event->status,
            $event->lokasi_file,
        ];
    }
}
