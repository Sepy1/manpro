<?php

namespace App\Exports;

use App\Models\VpnClient;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class VpnClientsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query()
    {
        return VpnClient::query()->with('group')->orderBy('name');
    }

    public function headings(): array
    {
        return ['Nama Pengguna', 'Username', 'Common Name', 'Departemen', 'Kantor Cabang', 'Nomor Inventaris', 'IP VPN', 'Group VPN', 'Allowed IP Destination', 'Status', 'Status Sertifikat', 'Status 2FA', 'Tanggal Dibuat'];
    }

    public function map($client): array
    {
        return [
            $client->name, $client->username, $client->common_name, $client->department,
            $client->branch_office, $client->inventory_number, $client->vpn_ip,
            $client->group?->name, implode(', ', $client->allowed_destinations ?: []),
            $client->status, $client->certificate_status,
            $client->mfa_enabled_at ? 'Aktif' : 'Belum aktif',
            $client->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
