<?php

namespace App\Imports;

use App\Models\Kantor;
use App\Models\KasKantor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Impor Kantor / Kantor Kas: baris pertama = header (dilewati), data pakai URUTAN KOLOM
 * yang sama seperti export/template. Ini menghindari kehilangan kolom
 * ketika HeadingRow Laravel-Excel menghasilkan key slug yang bentrok atau file pakai label lain.
 */
class KantorsImport implements ToCollection
{
    /** Urutan sama dengan headings export (KantorExport / template). */
    private const POSITIONAL_KEYS = [
        'kode_kantor',
        'nama_kantor',
        'aktif_kantor',
        'latitude_kantor',
        'longitude_kantor',
        'wireless_ip_kantor',
        'status_ping_kantor',
        'latency_ping_kantor_ms',
        'ping_diperiksa_kantor',
        'kode_kas',
        'nama_kas',
        'aktif_kas',
        'latitude_kas',
        'longitude_kas',
        'wireless_ip_kas',
        'status_ping_kas',
        'latency_ping_kas_ms',
        'ping_diperiksa_kas',
    ];

    private const LEGACY_POSITIONAL_KEYS = [
        'kode_kantor', 'nama_kantor', 'aktif_kantor', 'kode_kas', 'nama_kas', 'aktif_kas',
    ];

    /** Isi kemungkinan baris judul kolom Excel (huruf besar/kecil diabaikan); bukan cabang/KK. */
    private const SKIP_HEADER_FIRST_CELL = [
        'kode_kantor',
        'kode kantor',
        'kode cabang',
        'kode_cabang',
        'kd kantor',
        'kode kk',
        'no',
        'no.',
        '#',
    ];

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        /** @var Collection<int, mixed> $dataRows */
        $dataRows = $rows->values()->slice(1);

        $spreadsheetRow = 1;
        foreach ($dataRows as $row) {
            $spreadsheetRow++;
            /** @var array<int|string, mixed> $raw */
            $raw = $row instanceof Collection ? $row->toArray() : (array) $row;
            $values = Collection::wrap($raw)->values()->all();

            if ($this->rowLooksEmpty($values)) {
                continue;
            }

            $values = $this->normalizeLeadingBlanks($values);

            if ($this->rowLooksEmpty($values)) {
                continue;
            }

            $data = $this->combinePositionalKeys($values);

            if ($this->looksLikeColumnHeaderLabels($data)) {
                continue;
            }

            $this->processRow($data, $spreadsheetRow);
        }
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function combinePositionalKeys(array $values): array
    {
        $out = [];
        $keys = count($values) <= 7 ? self::LEGACY_POSITIONAL_KEYS : self::POSITIONAL_KEYS;

        foreach ($keys as $i => $key) {
            $out[$key] = $values[$i] ?? null;
        }

        // Format lama: kolom ke-7 nama singkat KK (otomatis kkode Kas 001)
        $out['kantor_kas'] = $keys === self::LEGACY_POSITIONAL_KEYS ? ($values[6] ?? null) : null;

        return $out;
    }

    /**
     * Hilangkan sel kosong di awal baris agar pemetaan posisi sama dengan kolom export
     * meskipun di kiri ada kolom kosong atau kolom "No".
     *
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    private function normalizeLeadingBlanks(array $values): array
    {
        $values = array_values($values);

        // Batas geser biar kolom KK yang memang bisa kosong di awal lain tidak menghapus banyak data salah.
        $maxShifts = min(15, max(0, count($values) - 3));

        while ($maxShifts-- > 0 && $values !== [] && $this->cellIsBlank($values[0])) {
            array_shift($values);
        }

        return $values;
    }

    private function cellIsBlank(mixed $v): bool
    {
        if ($v === null) {
            return true;
        }

        return trim((string) $v) === '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function looksLikeColumnHeaderLabels(array $data): bool
    {
        $first = strtolower(trim((string) ($data['kode_kantor'] ?? '')));

        if ($first === '') {
            return false;
        }

        foreach (self::SKIP_HEADER_FIRST_CELL as $hint) {
            if ($first === strtolower($hint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function rowLooksEmpty(array $values): bool
    {
        foreach ($values as $v) {
            if ($v === null) {
                continue;
            }

            $s = trim((string) $v);

            if ($s !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function processRow(array $data, int $spreadsheetRowNumber): void
    {
        $kodeCab = trim((string) ($data['kode_kantor'] ?? ''));
        if ($kodeCab === '') {
            return;
        }

        $namaCab = trim((string) ($data['nama_kantor'] ?? ''));
        $kodeKas = trim((string) ($data['kode_kas'] ?? ''));
        $namaKas = trim((string) ($data['nama_kas'] ?? ''));
        $latitudeCab = $this->parseCoordinate($data['latitude_kantor'] ?? null, -90, 90, 'latitude_kantor', $spreadsheetRowNumber);
        $longitudeCab = $this->parseCoordinate($data['longitude_kantor'] ?? null, -180, 180, 'longitude_kantor', $spreadsheetRowNumber);
        $wirelessIpCab = $this->parseIp($data['wireless_ip_kantor'] ?? null, 'wireless_ip_kantor', $spreadsheetRowNumber);
        $this->ensureCoordinatePair($latitudeCab, $longitudeCab, 'kantor cabang', $spreadsheetRowNumber);

        $legacy = trim((string) ($data['kantor_kas'] ?? ''));
        if ($legacy !== '' && $kodeKas === '') {
            $kodeKas = '001';
            $namaKas = $legacy;
        }

        $kantor = Kantor::query()
            ->whereRaw('LOWER(kode_kantor) = ?', [Str::lower($kodeCab)])
            ->first();

        if (! $kantor && $namaCab === '') {
            throw ValidationException::withMessages([
                'import_file' => "Baris {$spreadsheetRowNumber}: nama_kantor wajib untuk cabang baru dengan kode {$kodeCab}.",
            ]);
        }

        if (! $kantor) {
            $kantor = Kantor::create([
                'kode_kantor' => $kodeCab,
                'nama_kantor' => $namaCab,
                'is_active' => $this->parseBool($data['aktif_kantor'] ?? null, true),
                'latitude' => $latitudeCab,
                'longitude' => $longitudeCab,
                'wireless_ip' => $wirelessIpCab,
            ]);
        } else {
            $payloadCab = [];

            if ($namaCab !== '') {
                $payloadCab['nama_kantor'] = $namaCab;
            }

            if (array_key_exists('aktif_kantor', $data)) {
                $payloadCab['is_active'] = $this->parseBool($data['aktif_kantor'] ?? null, $kantor->is_active);
            }

            if ($latitudeCab !== null) {
                $payloadCab['latitude'] = $latitudeCab;
            }
            if ($longitudeCab !== null) {
                $payloadCab['longitude'] = $longitudeCab;
            }
            if ($wirelessIpCab !== null) {
                if ($kantor->wireless_ip !== $wirelessIpCab) {
                    $payloadCab += ['ping_status' => null, 'ping_latency_ms' => null, 'ping_checked_at' => null];
                }
                $payloadCab['wireless_ip'] = $wirelessIpCab;
            }

            if ($payloadCab !== []) {
                $kantor->update($payloadCab);
            }

            $kantor->refresh();
        }

        if ($kodeKas === '') {
            return;
        }

        $activeKas = $this->parseBool($data['aktif_kas'] ?? null, true);
        $latitudeKas = $this->parseCoordinate($data['latitude_kas'] ?? null, -90, 90, 'latitude_kas', $spreadsheetRowNumber);
        $longitudeKas = $this->parseCoordinate($data['longitude_kas'] ?? null, -180, 180, 'longitude_kas', $spreadsheetRowNumber);
        $wirelessIpKas = $this->parseIp($data['wireless_ip_kas'] ?? null, 'wireless_ip_kas', $spreadsheetRowNumber);
        $this->ensureCoordinatePair($latitudeKas, $longitudeKas, 'kantor kas', $spreadsheetRowNumber);

        $existingKas = KasKantor::query()
            ->where('kantor_id', $kantor->id)
            ->whereRaw('LOWER(kode_kas) = ?', [Str::lower($kodeKas)])
            ->first();

        if ($existingKas) {
            $payloadKas = [
                'nama_kas' => $namaKas !== '' ? $namaKas : $existingKas->nama_kas,
                'is_active' => $activeKas,
            ];
            if ($latitudeKas !== null) $payloadKas['latitude'] = $latitudeKas;
            if ($longitudeKas !== null) $payloadKas['longitude'] = $longitudeKas;
            if ($wirelessIpKas !== null) {
                if ($existingKas->wireless_ip !== $wirelessIpKas) {
                    $payloadKas += ['ping_status' => null, 'ping_latency_ms' => null, 'ping_checked_at' => null];
                }
                $payloadKas['wireless_ip'] = $wirelessIpKas;
            }
            $existingKas->update($payloadKas);

            return;
        }

        KasKantor::create([
            'kantor_id' => $kantor->id,
            'kode_kas' => $kodeKas,
            'nama_kas' => $namaKas !== '' ? $namaKas : null,
            'is_active' => $activeKas,
            'latitude' => $latitudeKas,
            'longitude' => $longitudeKas,
            'wireless_ip' => $wirelessIpKas,
        ]);
    }

    private function parseCoordinate(mixed $raw, float $min, float $max, string $field, int $row): ?float
    {
        $value = trim((string) ($raw ?? ''));
        if ($value === '') return null;
        if (! is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            throw ValidationException::withMessages(['import_file' => "Baris {$row}: {$field} tidak valid."]);
        }

        return (float) $value;
    }

    private function parseIp(mixed $raw, string $field, int $row): ?string
    {
        $value = trim((string) ($raw ?? ''));
        if ($value === '') return null;
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            throw ValidationException::withMessages(['import_file' => "Baris {$row}: {$field} harus berupa alamat IP yang valid."]);
        }

        return $value;
    }

    private function ensureCoordinatePair(?float $latitude, ?float $longitude, string $label, int $row): void
    {
        if (($latitude === null) !== ($longitude === null)) {
            throw ValidationException::withMessages([
                'import_file' => "Baris {$row}: latitude dan longitude {$label} harus diisi berpasangan.",
            ]);
        }
    }

    private function parseBool(mixed $raw, bool $default): bool
    {
        if ($raw === null) {
            return $default;
        }

        $v = Str::lower(trim((string) $raw));

        if ($v === '') {
            return $default;
        }

        if (in_array($v, ['1', 'true', 'ya', 'y', 'yes', 'aktif'], true)) {
            return true;
        }

        if (in_array($v, ['0', 'false', 'tidak', 'n', 'no', 'nonaktif'], true)) {
            return false;
        }

        return $default;
    }
}
