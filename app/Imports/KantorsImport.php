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
 * yang sama seperti export/template (kolom ke-6). Ini menghindari kehilangan kolom
 * ketika HeadingRow Laravel-Excel menghasilkan key slug yang bentrok atau file pakai label lain.
 */
class KantorsImport implements ToCollection
{
    /** Urutan sama dengan headings export (KantorExport / template). */
    private const POSITIONAL_KEYS = [
        'kode_kantor',
        'nama_kantor',
        'aktif_kantor',
        'kode_kas',
        'nama_kas',
        'aktif_kas',
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

        foreach (self::POSITIONAL_KEYS as $i => $key) {
            $out[$key] = $values[$i] ?? null;
        }

        // Format lama: kolom ke-7 nama singkat KK (otomatis kkode Kas 001)
        $out['kantor_kas'] = $values[count(self::POSITIONAL_KEYS)] ?? null;

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
            ]);
        } else {
            $payloadCab = [];

            if ($namaCab !== '') {
                $payloadCab['nama_kantor'] = $namaCab;
            }

            if (array_key_exists('aktif_kantor', $data)) {
                $payloadCab['is_active'] = $this->parseBool($data['aktif_kantor'] ?? null, $kantor->is_active);
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

        $existingKas = KasKantor::query()
            ->where('kantor_id', $kantor->id)
            ->whereRaw('LOWER(kode_kas) = ?', [Str::lower($kodeKas)])
            ->first();

        if ($existingKas) {
            $existingKas->update([
                'nama_kas' => $namaKas !== '' ? $namaKas : $existingKas->nama_kas,
                'is_active' => $activeKas,
            ]);

            return;
        }

        KasKantor::create([
            'kantor_id' => $kantor->id,
            'kode_kas' => $kodeKas,
            'nama_kas' => $namaKas !== '' ? $namaKas : null,
            'is_active' => $activeKas,
        ]);
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
