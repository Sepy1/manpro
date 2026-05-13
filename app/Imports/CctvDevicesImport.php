<?php

namespace App\Imports;

use App\Models\CctvDevice;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CctvDevicesImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        $branch = trim((string) ($row['cabang'] ?? ''));
        $office = trim((string) ($row['kantor_kas'] ?? ''));
        $dvrBrand = trim((string) ($row['merk_dvr'] ?? ''));
        $harddisk = trim((string) ($row['harddisk'] ?? ''));
        $monitor = trim((string) ($row['monitor'] ?? ''));

        $payload = [
            'branch' => $branch,
            'office' => $office,
            'dvr_brand' => $dvrBrand !== '' ? $dvrBrand : '-',
            'channel_count' => $this->normalizeNullableInteger($row['jumlah_channel'] ?? null),
            'harddisk' => $harddisk !== '' ? $harddisk : '-',
            'monitor' => $monitor !== '' ? $monitor : '-',
            'connection_status' => $this->normalizeNullableString($row['koneksi'] ?? null),
            'device_status' => $this->normalizeNullableString($row['status'] ?? null),
            'notes' => $row['keterangan'] ?? null,
        ];

        $existing = CctvDevice::query()
            ->whereRaw('LOWER(branch) = ?', [strtolower($branch)])
            ->whereRaw('LOWER(office) = ?', [strtolower($office)])
            ->first();

        if ($existing) {
            $existing->update($payload);
            return null;
        }

        return new CctvDevice($payload);
    }

    public function rules(): array
    {
        return [
            'cabang' => ['required', 'string', 'max:255'],
            'kantor_kas' => ['required', 'string', 'max:255'],
            'merk_dvr' => ['nullable', 'string', 'max:255'],
            'jumlah_channel' => ['nullable', 'integer', 'min:1', 'max:256'],
            'harddisk' => ['nullable', 'string', 'max:255'],
            'monitor' => ['nullable', 'string', 'max:255'],
            'koneksi' => ['nullable', 'string', 'max:255'],
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
}
