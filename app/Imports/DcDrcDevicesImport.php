<?php

namespace App\Imports;

use App\Models\DcDrcDevice;
use App\Models\DcDrcDeviceType;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;

class DcDrcDevicesImport implements ToModel, WithEvents, WithHeadingRow, WithValidation
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
            'site' => $this->normalizeLocation($row['lokasi'] ?? null),
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
            'tipe' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $exists = DcDrcDeviceType::query()
                        ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $value))])
                        ->exists();

                    if (!$exists) {
                        $fail('Tipe perangkat tidak terdaftar pada Parameter Tipe Perangkat DC/DRC.');
                    }
                },
            ],
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
            'lokasi' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = strtolower(trim((string) $value));
                    $isDc = in_array($normalized, ['dc', 'data center'], true);
                    $isDrc = in_array($normalized, ['drc', 'disaster recovery', 'disaster recovery center'], true);

                    if (!$isDc && !$isDrc) {
                        $fail('Lokasi wajib diisi DC atau DRC.');
                    }
                },
            ],
            'fungsi' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'max:255'],
            'owner_team' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => static function (): void {
                DcDrcDevice::query()
                    ->whereRaw("LOWER(device_type) = 'vm'")
                    ->whereNull('vm_host_id')
                    ->whereNotNull('host_server')
                    ->orderBy('id')
                    ->each(function (DcDrcDevice $vm): void {
                        $hostId = DcDrcDevice::query()
                            ->whereRaw("LOWER(device_type) = 'vm host'")
                            ->whereRaw('LOWER(server_name) = ?', [strtolower((string) $vm->host_server)])
                            ->value('id');

                        if ($hostId) {
                            $vm->update(['vm_host_id' => $hostId]);
                        }
                    });
            },
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

        $normalized = match ($compactValue) {
            'vm' => 'VM',
            'vmhost' => 'vm host',
            'baremetal' => 'bare metal',
            'physical' => 'physical',
            default => $rawValue,
        };

        return DcDrcDeviceType::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($normalized)])
            ->value('name') ?? $normalized;
    }

    private function normalizeLocation(mixed $location): string
    {
        return DcDrcDevice::resolveRoom($this->normalizeNullableString($location));
    }
}
