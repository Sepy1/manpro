<?php

namespace App\Imports;

use App\Models\DcDrcDevice;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DcDrcDevicesImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        $serverName = trim((string) ($row['server_name'] ?? ''));
        $ipAddress = $this->normalizeNullableString($row['ip_address'] ?? null);
        $deviceType = $this->normalizeDeviceType($this->normalizeNullableString($row['tipe'] ?? null));
        $hostServer = $this->normalizeNullableString($row['vm_server'] ?? null);
        $vmHostId = null;

        if (strtolower((string) $deviceType) === 'vm' && $hostServer) {
            $vmHostId = DcDrcDevice::query()
                ->whereRaw("LOWER(device_type) = 'vm host'")
                ->whereRaw('LOWER(server_name) = ?', [strtolower($hostServer)])
                ->value('id');
        }

        $payload = [
            'server_name' => $serverName,
            'device_type' => $deviceType,
            'host_server' => $hostServer,
            'vm_host_id' => $vmHostId,
            'ip_address' => $ipAddress,
            'vlan' => $this->normalizeNullableString($row['vlan'] ?? null),
            'nic_model' => $this->normalizeNullableString($row['nic_model'] ?? null),
            'os' => $this->normalizeNullableString($row['os'] ?? null),
            'cpu_cores' => $this->normalizeNullableInteger($row['cpu'] ?? null),
            'ram_gb' => $this->normalizeNullableInteger($row['ram_gb'] ?? null),
            'storage_gb' => $this->normalizeNullableInteger($row['hardisk_gb'] ?? null),
            'objid_cpu' => $this->normalizeNullableString($row['objid_cpu'] ?? null),
            'objid_ram' => $this->normalizeNullableString($row['objid_ram'] ?? null),
            'objid_ping' => $this->normalizeNullableString($row['objid_ping'] ?? null),
            'objid_diskfree' => $this->normalizeNullableString($row['objid_diskfree'] ?? null),
            'objid_traffic' => $this->normalizeNullableString($row['objid_traffic'] ?? null),
            'site' => $this->normalizeNullableString($row['lokasi'] ?? null),
            'system_role' => $this->normalizeNullableString($row['fungsi'] ?? null),
            'environment' => $this->normalizeNullableString($row['environment'] ?? null),
            'owner_team' => $this->normalizeNullableString($row['owner_team'] ?? null),
            'status' => $this->normalizeNullableString($row['status'] ?? null),
            'notes' => $this->normalizeNullableString($row['keterangan'] ?? null),
        ];

        $existing = DcDrcDevice::query()
            ->whereRaw('LOWER(server_name) = ?', [strtolower($serverName)])
            ->when($ipAddress !== null, function ($query) use ($ipAddress) {
                $query->whereRaw('LOWER(ip_address) = ?', [strtolower($ipAddress)]);
            })
            ->first();

        if ($existing) {
            $existing->update($payload);
            return null;
        }

        return new DcDrcDevice($payload);
    }

    public function rules(): array
    {
        return [
            'server_name' => ['required', 'string', 'max:255'],
            'tipe' => ['nullable', 'string', 'max:255'],
            'vm_server' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:255'],
            // Excel often parses VLAN as numeric, so allow mixed scalar input.
            'vlan' => ['nullable'],
            'nic_model' => ['nullable', 'string', 'max:255'],
            'os' => ['nullable', 'string', 'max:255'],
            'cpu' => ['nullable', 'integer', 'min:1', 'max:2048'],
            'ram_gb' => ['nullable', 'integer', 'min:1', 'max:1048576'],
            'hardisk_gb' => ['nullable', 'integer', 'min:1', 'max:10485760'],
            // Excel often parses OBJID as numeric, so allow mixed scalar input.
            'objid_cpu' => ['nullable', 'max:255'],
            'objid_ram' => ['nullable', 'max:255'],
            'objid_ping' => ['nullable', 'max:255'],
            'objid_diskfree' => ['nullable', 'max:255'],
            'objid_traffic' => ['nullable', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'fungsi' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'max:255'],
            'owner_team' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeDeviceType(?string $deviceType): ?string
    {
        $rawValue = trim((string) $deviceType);
        if ($rawValue === '') {
            return null;
        }

        $compactValue = strtolower(preg_replace('/[\s_-]+/', '', $rawValue) ?? $rawValue);

        return match ($compactValue) {
            'vm' => 'VM',
            'vmhost' => 'vm host',
            'baremetal' => 'bare metal',
            'physical' => 'physical',
            default => $rawValue,
        };
    }
}
