<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SecurityAttackLogsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return (clone $this->query)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return [
            'Waktu Event',
            'Device',
            'Severity',
            'Attack',
            'Attack ID',
            'Subtype',
            'Action',
            'Source IP',
            'Source Port',
            'Destination IP',
            'Destination Port',
            'Service',
            'Method',
            'URL',
            'Diterima',
        ];
    }

    public function map($log): array
    {
        return [
            $log->event_time,
            $log->device,
            $log->severity,
            $log->attack,
            $log->attackid,
            $log->subtype,
            $log->action,
            $log->srcip,
            $log->srcport,
            $log->dstip,
            $log->dstport,
            $log->service,
            $log->method,
            $log->url,
            $log->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
