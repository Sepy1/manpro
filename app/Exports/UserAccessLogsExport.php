<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserAccessLogsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return (clone $this->query)
            ->orderByDesc('event_date')
            ->orderByDesc('event_clock')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'Waktu', 'Zona Waktu', 'Username', 'Status', 'Perangkat',
            'Device ID', 'Vendor', 'Virtual Domain', 'Source IP', 'Destination IP',
            'Tunnel IP', 'Source Interface', 'Destination Interface', 'Method',
            'Action', 'Event Type', 'Event Category', 'Auth Server', 'Original Status',
            'Reason', 'Profile', 'Event Time', 'Log ID', 'Log Description', 'Severity',
            'Message', 'Raw Log', 'Diterima',
        ];
    }

    public function map($log): array
    {
        return [
            $log->event_date?->format('Y-m-d'), $log->event_clock, $log->timezone,
            $log->username, $log->status, $log->device_name, $log->device_id,
            $log->vendor, $log->virtual_domain, $log->source_ip, $log->destination_ip,
            $log->tunnel_ip, $log->source_interface, $log->destination_interface,
            $log->method, $log->action, $log->event_type, $log->event_category,
            $log->auth_server, $log->original_status, $log->reason, $log->profile,
            $log->event_time, $log->log_id, $log->log_description, $log->severity,
            $log->message, $log->raw_log, $log->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
